<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\Api\ApiProblemException;
use App\Service\Api\PlayerMessageSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * A player of the game sends any action message: `{"type": "pass_opportunity", "payload": {...}}`.
 * See ApiMessageFactory for the payload, PlayerMessageSender for the errors. Errors are problem details (RFC 9457).
 *
 * The ETag of the response is the version of the game once the message is handled. With `If-Match` (the ETag of the
 * version the player saw), the message is rejected if the game has changed since.
 */
class ApiGameMessagesController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly PlayerMessageSender $messageSender,
    ) {
    }

    #[Route('/api/games/{id}/messages', name: 'app_api_game_messages', methods: ['POST'])]
    public function post(string $id, Request $request, #[CurrentUser] UserInterface $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $type = $body['type'] ?? null;
        $payload = $body['payload'] ?? [];
        if (! is_string($type) || ! is_array($payload)) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_body', 'The body must have a string "type" and an object "payload".');
        }

        $ifMatch = $request->headers->get('If-Match');
        $expectedVersion = null;
        if ($ifMatch !== null && trim($ifMatch) !== '*') {
            if (preg_match('/^\s*"(\d+)"\s*$/', $ifMatch, $matches) !== 1) {
                return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_if_match', 'If-Match must be a single ETag of the game, like "12".');
            }
            $expectedVersion = (int) $matches[1];
        }

        try {
            $version = $this->messageSender->send($id, $user->getUserIdentifier(), $type, $payload, $expectedVersion);
        } catch (ApiProblemException $problem) {
            return $this->problem($problem->getStatus(), $problem->getType(), $problem->getMessage(), $problem->getExtensions());
        }

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->setEtag((string) $version);

        return $response;
    }
}
