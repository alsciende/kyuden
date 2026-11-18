<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\User;
use App\Exception\Api\InvalidTournamentStartException;
use App\Exception\Api\NotOrganizerException;
use App\Exception\Api\NotRegisteredException;
use App\Exception\Api\TournamentAlreadyOrganizedException;
use App\Exception\Api\TournamentClosedException;
use App\Exception\Api\TournamentFullException;
use App\Exception\Api\TournamentTooCloseException;
use App\Exception\Data\DeckNotFoundException;
use App\Exception\Data\TournamentNotFoundException;
use App\Repository\TournamentEntryRepository;
use App\Repository\TournamentRepository;
use App\Service\Tournament\TournamentOrganizer;
use App\Service\Tournament\TournamentRegistrar;
use App\View\TournamentViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The tournaments: anyone reads them (public, visitors included); an account creates one with its start only
 * (`{"starts_at": "2026-11-21T19:00:00Z"}`, TournamentOrganizer) and cancels it, registers in one with a deck
 * (`PUT /api/self/tournaments/{id}`, `{"deck_id": ...}`: registers or changes the deck, TournamentRegistrar) and
 * unregisters. What concerns the reader (their registrations, their decks) is under `/api/self`, so that the view of a
 * tournament is the same for everybody. Errors are problem details (RFC 9457).
 */
class ApiTournamentsController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly TournamentEntryRepository $tournamentEntryRepository,
        private readonly TournamentOrganizer $tournamentOrganizer,
        private readonly TournamentRegistrar $tournamentRegistrar,
        private readonly TournamentViewFactory $tournamentViewFactory,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * The tournaments to be played (upcoming, locked, in progress) and the cancelled ones that would not have begun
     * yet, the first to begin first.
     */
    #[Route('/api/tournaments', name: 'app_api_tournaments', methods: ['GET'])]
    public function index(): Response
    {
        $tournaments = $this->tournamentRepository->findListed($this->clock->now());

        return $this->json($this->tournamentViewFactory->createSummaries($tournaments), headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/tournaments/{id}', name: 'app_api_tournament', methods: ['GET'])]
    public function show(string $id): Response
    {
        $tournament = $this->tournamentRepository->find($id);
        if (! $tournament instanceof Tournament) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'tournament_not_found', "Tournament not found: [{$id}]");
        }

        return $this->json($this->tournamentViewFactory->create($tournament), headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/tournaments', name: 'app_api_tournaments_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $startsAt = is_string($body['starts_at'] ?? null) ? $this->parseDate($body['starts_at']) : null;
        if (! $startsAt instanceof \DateTimeImmutable || array_diff(array_keys($body), ['starts_at']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a "starts_at": a date and time with its offset (RFC 3339).', [
                'violations' => [['property_path' => 'starts_at', 'message' => 'When the tournament begins, e.g. "2026-11-21T19:00:00Z".']],
            ]);
        }

        if (! $user->canTakePartInTournaments()) {
            return $this->accountRequired();
        }

        try {
            $tournament = $this->tournamentOrganizer->create($user, $startsAt);
        } catch (InvalidTournamentStartException $exception) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_start', $exception->getMessage());
        } catch (TournamentAlreadyOrganizedException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'tournament_already_organized', $exception->getMessage());
        } catch (TournamentTooCloseException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'tournament_too_close', $exception->getMessage(), [
                'other_starts_at' => $exception->getOtherStartsAt()->format(\DateTimeInterface::RFC3339),
            ]);
        }

        return $this->json($this->tournamentViewFactory->create($tournament), Response::HTTP_CREATED);
    }

    #[Route('/api/tournaments/{id}/cancellation', name: 'app_api_tournament_cancel', methods: ['POST'])]
    public function cancel(string $id, #[CurrentUser] User $user): Response
    {
        try {
            $tournament = $this->tournamentOrganizer->cancel($id, $user);
        } catch (TournamentNotFoundException $exception) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'tournament_not_found', $exception->getMessage());
        } catch (NotOrganizerException $exception) {
            return $this->problem(Response::HTTP_FORBIDDEN, 'not_organizer', $exception->getMessage());
        } catch (TournamentClosedException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'tournament_closed', $exception->getMessage());
        }

        return $this->json($this->tournamentViewFactory->create($tournament));
    }

    /**
     * The registrations of the user in the tournaments that have not ended, cancelled ones included (the web client
     * warns them until they unregister), the first to begin first.
     */
    #[Route('/api/self/tournaments', name: 'app_api_self_tournaments', methods: ['GET'])]
    public function entries(#[CurrentUser] User $user): Response
    {
        $entries = $this->tournamentEntryRepository->findNotEndedOfUser($user);

        return $this->json($this->tournamentViewFactory->createEntries($entries), headers: ['Cache-Control' => 'no-store']);
    }

    /**
     * Registers the user with a deck, or changes the deck of their registration.
     */
    #[Route('/api/self/tournaments/{id}', name: 'app_api_self_tournament_register', methods: ['PUT'])]
    public function register(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $deckId = $body['deck_id'] ?? null;
        if (! is_string($deckId) || array_diff(array_keys($body), ['deck_id']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a string "deck_id".', [
                'violations' => [['property_path' => 'deck_id', 'message' => 'The id of a deck of the user, or of a starter deck.']],
            ]);
        }

        if (! $user->canTakePartInTournaments()) {
            return $this->accountRequired();
        }

        try {
            [$entry, $created] = $this->tournamentRegistrar->register($id, $user, $deckId);
        } catch (TournamentNotFoundException $exception) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'tournament_not_found', $exception->getMessage());
        } catch (DeckNotFoundException $exception) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found', $exception->getMessage());
        } catch (TournamentClosedException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'tournament_closed', $exception->getMessage());
        } catch (TournamentFullException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'tournament_full', $exception->getMessage());
        }

        return $this->json($this->tournamentViewFactory->createEntries([$entry])[0], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/api/self/tournaments/{id}', name: 'app_api_self_tournament_unregister', methods: ['DELETE'])]
    public function unregister(string $id, #[CurrentUser] User $user): Response
    {
        try {
            $this->tournamentRegistrar->unregister($id, $user);
        } catch (TournamentNotFoundException $exception) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'tournament_not_found', $exception->getMessage());
        } catch (NotRegisteredException $exception) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'not_registered', $exception->getMessage());
        } catch (TournamentClosedException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'tournament_closed', $exception->getMessage());
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * A date and time with its offset (RFC 3339, `Z` or `+01:00`), null when it is not one or does not exist (the 31st
     * of November).
     */
    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!' . \DateTimeInterface::RFC3339, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date instanceof \DateTimeImmutable && ($errors === false || $errors['warning_count'] === 0) ? $date : null;
    }

    private function accountRequired(): JsonResponse
    {
        return $this->problem(Response::HTTP_FORBIDDEN, 'account_required', 'Only an account signed in to with a username takes part in tournaments.');
    }
}
