<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\User;
use App\Repository\CandidateRepository;
use App\Repository\DeckRepository;
use App\Service\MatchMaking\MatchMakingInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The matchmaking queue: the authenticated user joins it with one of the decks they can use (`{"deck_id": ...}`), and
 * is matched with the next candidate at once (MatchMakingInterface). A user waits in the queue once; once matched,
 * they are no longer in it. Errors are problem details (RFC 9457).
 */
class ApiQueueController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly CandidateRepository $candidateRepository,
        private readonly DeckRepository $deckRepository,
        private readonly MatchMakingInterface $matchMaking,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/queue', name: 'app_api_queue', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): Response
    {
        $candidate = $this->candidateRepository->findOneBy(['user' => $user]);
        if (! $candidate instanceof Candidate) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'not_queued', 'The user is not in the queue.');
        }

        return $this->json(['deck_id' => $candidate->getDeck()->getId()]);
    }

    #[Route('/api/queue', name: 'app_api_queue_join', methods: ['POST'])]
    public function join(Request $request, #[CurrentUser] User $user): Response
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

        $deck = $this->deckRepository->findUsable($deckId, $user);
        if (! $deck instanceof Deck) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found', "Deck not found: [{$deckId}]");
        }

        if ($this->candidateRepository->count(['user' => $user]) > 0) {
            return $this->problem(Response::HTTP_CONFLICT, 'already_queued', 'The user is already in the queue.');
        }

        $candidate = new Candidate();
        $candidate->setUser($user);
        $candidate->setDeck($deck);
        $this->entityManager->persist($candidate);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // joined concurrently
            return $this->problem(Response::HTTP_CONFLICT, 'already_queued', 'The user is already in the queue.');
        }

        $this->matchMaking->findMatches();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/queue', name: 'app_api_queue_leave', methods: ['DELETE'])]
    public function leave(#[CurrentUser] User $user): Response
    {
        $candidate = $this->candidateRepository->findOneBy(['user' => $user]);
        if (! $candidate instanceof Candidate) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'not_queued', 'The user is not in the queue.');
        }

        $this->candidateRepository->withQueueLock(function () use ($candidate): void {
            $this->entityManager->remove($candidate);
            $this->entityManager->flush();
        });

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
