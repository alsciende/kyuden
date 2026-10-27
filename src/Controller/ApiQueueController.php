<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Exception\Api\AlreadyQueuedException;
use App\Exception\Api\InvalidInvitationException;
use App\Exception\Api\NotQueuedException;
use App\Exception\Data\DeckNotFoundException;
use App\Repository\InvitationRepository;
use App\Security\InvitationCode;
use App\Service\MatchMaking\MatchMakingQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The matchmaking queue (MatchMakingQueue): the authenticated user joins it with one of the decks they can use
 * (`{"deck_id": ...}`), and is matched with the next candidate at once. The host of an invitation joins it with its id
 * (`invitation_id`), a friend with its code (`invitation_code`): they are matched together only. Errors are problem
 * details (RFC 9457).
 */
class ApiQueueController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly MatchMakingQueue $queue,
        private readonly InvitationRepository $invitationRepository,
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
        $invitationId = $body['invitation_id'] ?? null;
        $invitationCode = $body['invitation_code'] ?? null;
        if (! is_string($deckId) || ! (is_string($invitationId) || $invitationId === null) || ! (is_string($invitationCode) || $invitationCode === null)
            || ($invitationId !== null && $invitationCode !== null) || array_diff(array_keys($body), ['deck_id', 'invitation_id', 'invitation_code']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must have a string "deck_id", and may have a string "invitation_id" or "invitation_code".', [
                'violations' => [
                    ['property_path' => 'deck_id', 'message' => 'The id of a deck of the user, or of a starter deck.'],
                    ['property_path' => 'invitation_id', 'message' => 'The id of the invitation of the user, its host.'],
                    ['property_path' => 'invitation_code', 'message' => 'The code of the invitation of a friend.'],
                ],
            ]);
        }

        $invitation = match (true) {
            $invitationId !== null => $this->invitationRepository->findOneBy(['id' => $invitationId, 'host' => $user]),
            $invitationCode !== null => $this->invitationRepository->findOneByCode(InvitationCode::normalize($invitationCode)),
            default => null,
        };
        if (($invitationId ?? $invitationCode) !== null && ! $invitation instanceof Invitation) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_invitation', 'No invitation has this id or this code: it may have been cancelled.');
        }

        try {
            $this->queue->join($user, $deckId, $invitation);
        } catch (DeckNotFoundException $exception) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found', $exception->getMessage());
        } catch (InvalidInvitationException $exception) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_invitation', $exception->getMessage());
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
