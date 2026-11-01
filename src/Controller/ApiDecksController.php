<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\CardSource;
use App\Enum\DeckImportOutcome;
use App\Exception\Api\DeckInQueueException;
use App\Exception\Api\DeckSourceUnavailableException;
use App\Exception\Api\InvalidDeckException;
use App\Exception\Api\InvalidDeckUrlException;
use App\Repository\DeckRepository;
use App\Service\Deck\DeckDeleter;
use App\Service\Deck\DeckImporter;
use App\Service\Deck\EmeraldDbDeckFetcher;
use App\View\DeckViewFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The decks of the authenticated user, and the decks without owner (the starter decks) every user can use. A deck is
 * imported from its cards in one of the formats of DeckImporter (`{"name": ..., "format": "emeralddb", "cards":
 * {"card id": quantity}}`), or downloaded from the URL of its page on EmeraldDB (`{"name": ..., "url": ...}`, the name
 * of EmeraldDB unless given), and checked against the deckbuilding rules: a deck the user already has is answered
 * instead (200), restored when they had deleted it. A user deletes their own decks (DeckDeleter). Errors are problem
 * details (RFC 9457).
 */
class ApiDecksController extends AbstractController
{
    use ProblemResponseTrait;

    public const int MAX_NAME_LENGTH = 255;

    public const int MAX_URL_LENGTH = 2048;

    public function __construct(
        private readonly DeckRepository $deckRepository,
        private readonly DeckImporter $deckImporter,
        private readonly EntityManagerInterface $entityManager,
        private readonly DeckViewFactory $deckViewFactory,
        private readonly EmeraldDbDeckFetcher $emeraldDbDeckFetcher,
        private readonly DeckDeleter $deckDeleter,
    ) {
    }

    #[Route('/api/decks', name: 'app_api_decks', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): Response
    {
        return $this->json(array_map($this->deckViewFactory->create(...), $this->deckRepository->findUsableBy($user)));
    }

    #[Route('/api/decks/{id}', name: 'app_api_deck', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): Response
    {
        $deck = $this->deckRepository->findUsable($id, $user);
        if (! $deck instanceof Deck) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'deck_not_found', "Deck not found: [{$id}]");
        }

        return $this->json($this->deckViewFactory->create($deck));
    }

    #[Route('/api/decks', name: 'app_api_decks_import', methods: ['POST'])]
    public function import(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $violations = $this->getStructureViolations($body);
        if ($violations !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Invalid deck.', ['violations' => $violations]);
        }
        /** @var array{name?: string|null, url?: string, format?: string, cards?: array<string, int>} $body */

        try {
            if (isset($body['url'])) {
                $fetched = $this->emeraldDbDeckFetcher->fetch($body['url']);
                $imported = $this->deckImporter->import($user, $body['name'] ?? $fetched['name'], CardSource::EmeraldDb->value, $fetched['cards']);
            } else {
                $imported = $this->deckImporter->import($user, $body['name'] ?? null, $body['format'] ?? '', $body['cards'] ?? []);
            }
        } catch (InvalidDeckUrlException $exception) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck', 'The deck cannot be imported.', ['violations' => [['property_path' => 'url', 'message' => $exception->getMessage()]]]);
        } catch (InvalidDeckException $exception) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck', $exception->getMessage(), ['violations' => $exception->getViolations()]);
        } catch (DeckSourceUnavailableException $exception) {
            return $this->problem(Response::HTTP_BAD_GATEWAY, 'deck_source_unavailable', $exception->getMessage());
        }
        $this->entityManager->flush();

        $deck = $imported->deck;
        $status = $imported->outcome === DeckImportOutcome::AlreadyOwned ? Response::HTTP_OK : Response::HTTP_CREATED;
        $response = $this->json($this->deckViewFactory->create($deck), $status);
        $response->headers->set('Location', $this->generateUrl('app_api_deck', ['id' => $deck->getId()]));

        return $response;
    }

    #[Route('/api/decks/{id}', name: 'app_api_deck_delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): Response
    {
        $deck = $this->deckRepository->findUsable($id, $user);
        // the starter decks are every user's
        if (! $deck instanceof Deck || ! $deck->getOwner() instanceof User) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'deck_not_found', "Deck not found: [{$id}]");
        }

        try {
            $this->deckDeleter->delete($deck);
        } catch (DeckInQueueException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'deck_in_queue', $exception->getMessage());
        }
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $body
     *
     * @return list<array{property_path: string, message: string}>
     */
    private function getStructureViolations(array $body): array
    {
        $violations = [];
        $byUrl = array_key_exists('url', $body);
        foreach (array_diff(array_map(strval(...), array_keys($body)), $byUrl ? ['name', 'url'] : ['name', 'format', 'cards']) as $field) {
            $violations[] = ['property_path' => $field, 'message' => $byUrl ? 'This field is not expected with a URL.' : 'This field is not expected.'];
        }

        $name = $body['name'] ?? null;
        if ($name !== null && (! is_string($name) || mb_strlen($name) > self::MAX_NAME_LENGTH)) {
            $violations[] = ['property_path' => 'name', 'message' => sprintf('The name must be a string of at most %d characters.', self::MAX_NAME_LENGTH)];
        }

        if ($byUrl) {
            if (! is_string($body['url']) || $body['url'] === '' || strlen($body['url']) > self::MAX_URL_LENGTH) {
                $violations[] = ['property_path' => 'url', 'message' => sprintf('The URL must be a string of 1 to %d characters.', self::MAX_URL_LENGTH)];
            }

            return $violations;
        }

        if (! is_string($body['format'] ?? null)) {
            $violations[] = ['property_path' => 'format', 'message' => sprintf('The format must be one of: %s.', implode(', ', $this->deckImporter->getFormatNames()))];
        }

        $cards = $body['cards'] ?? null;
        if (! is_array($cards) || $cards === [] || array_is_list($cards)) {
            $violations[] = ['property_path' => 'cards', 'message' => 'The cards must be an object of quantities by card id.'];
        } else {
            foreach ($cards as $id => $quantity) {
                if (! is_int($quantity) || $quantity < 1) {
                    $violations[] = ['property_path' => "cards[{$id}]", 'message' => 'The quantity must be a positive integer.'];
                }
            }
        }

        return $violations;
    }
}
