<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\Data\GameMismatchException;
use App\Exception\Data\NotFoundException;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\RulesException;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\Api\ApiMessageFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Workflow\Exception\TransitionException;

use function Symfony\Component\String\u;

/**
 * A player of the game sends any action message: `{"type": "pass_opportunity", "payload": {...}}`.
 * See ApiMessageFactory for the payload. Errors are problem details (RFC 9457).
 */
class ApiGameMessagesController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly ApiMessageFactory $messageFactory,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/api/games/{id}/messages', name: 'app_api_game_messages', methods: ['POST'])]
    public function post(string $id, Request $request, #[CurrentUser] UserInterface $user): Response
    {
        // no entity is loaded before the message locks the game (GameLockMiddleware)
        if ($this->gameRepository->count(['id' => $id]) === 0) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        $playerId = $this->playerRepository->findIdByGameAndUser($id, $user->getUserIdentifier());
        if ($playerId === null) {
            return $this->problem(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game.');
        }

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

        try {
            $this->bus->dispatch($this->messageFactory->create($type, $payload, $id, $playerId));
        } catch (\Throwable $throwable) {
            return $this->toProblem($throwable instanceof HandlerFailedException ? $throwable->getPrevious() ?? $throwable : $throwable);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function toProblem(\Throwable $throwable): Response
    {
        $status = match (true) {
            $throwable instanceof HttpExceptionInterface => $throwable->getStatusCode(),
            // the action is not allowed in the current state of the game
            $throwable instanceof RulesException, $throwable instanceof WorkflowException, $throwable instanceof TransitionException => Response::HTTP_CONFLICT,
            // the payload refers to something that does not exist in this game, or has invalid values
            $throwable instanceof NotFoundException, $throwable instanceof GameMismatchException, $throwable instanceof \InvalidArgumentException => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => throw $throwable,
        };

        $type = $throwable instanceof HttpExceptionInterface
            ? u(Response::$statusTexts[$status] ?? 'error')->snake()->toString()
            : u(new \ReflectionClass($throwable)->getShortName())->trimSuffix('Exception')->snake()->toString();

        return $this->problem($status, $type, $throwable->getMessage());
    }
}
