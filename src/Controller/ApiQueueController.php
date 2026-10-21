<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\Api\AlreadyQueuedException;
use App\Exception\Api\NotQueuedException;
use App\Exception\Data\DeckNotFoundException;
use App\Service\MatchMaking\MatchMakingQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The matchmaking queue (MatchMakingQueue): the authenticated user joins it with one of the decks they can use
 * (`{"deck_id": ...}`), and is matched with the next candidate at once. Errors are problem details (RFC 9457).
 */
class ApiQueueController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly MatchMakingQueue $queue,
    ) {
    }

    #[Route('/api/queue', name: 'app_api_queue', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): Response
    {
        $deckId = $this->queue->findDeckId($user);
        if ($deckId === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'not_queued', 'The user is not in the queue.');
        }

        return $this->json(['deck_id' => $deckId]);
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

        try {
            $this->queue->join($user, $deckId);
        } catch (DeckNotFoundException $exception) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found', $exception->getMessage());
        } catch (AlreadyQueuedException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'already_queued', $exception->getMessage());
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/queue', name: 'app_api_queue_leave', methods: ['DELETE'])]
    public function leave(#[CurrentUser] User $user): Response
    {
        try {
            $this->queue->leave($user);
        } catch (NotQueuedException $exception) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'not_queued', $exception->getMessage());
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
