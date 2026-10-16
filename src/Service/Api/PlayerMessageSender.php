<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Exception\Api\ApiProblemException;
use App\Exception\Api\InvalidPayloadException;
use App\Exception\Api\StaleVersionException;
use App\Exception\Data\GameMismatchException;
use App\Exception\Data\NotFoundException;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\RulesException;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Workflow\Exception\TransitionException;

use function Symfony\Component\String\u;

/**
 * Sends an action message for a user who plays a game, as the APIs receive it (HTTP and MCP): its type and payload
 * (see ApiMessageFactory), sent by the player of the user. A rejected message is an ApiProblemException, whose status
 * is the HTTP status of the problem.
 */
final readonly class PlayerMessageSender
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private ApiMessageFactory $messageFactory,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @param array<mixed> $payload
     * @param int|null     $expectedVersion the version of the game the sender saw: the message is rejected if the
     *                                      game has changed since
     *
     * @return int the version of the game once the message is handled
     *
     * @throws ApiProblemException
     */
    public function send(string $gameId, string $userId, string $type, array $payload, ?int $expectedVersion = null): int
    {
        // no entity is loaded before the message locks the game (GameLockMiddleware)
        if ($this->gameRepository->count(['id' => $gameId]) === 0) {
            throw new ApiProblemException(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$gameId}]");
        }

        $playerId = $this->playerRepository->findIdByGameAndUser($gameId, $userId);
        if ($playerId === null) {
            throw new ApiProblemException(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game.');
        }

        try {
            $this->bus->dispatch(
                $this->messageFactory->create($type, $payload, $gameId, $playerId),
                $expectedVersion === null ? [] : [new ExpectedVersionStamp($expectedVersion)],
            );
        } catch (\Throwable $throwable) {
            throw $this->toProblem($throwable instanceof HandlerFailedException ? $throwable->getPrevious() ?? $throwable : $throwable);
        }

        // the game is now loaded: locked by the handling of the message
        return $this->gameRepository->get($gameId)->getVersion();
    }

    private function toProblem(\Throwable $throwable): ApiProblemException
    {
        $status = match (true) {
            $throwable instanceof HttpExceptionInterface => $throwable->getStatusCode(),
            // the game has changed since the version the player saw
            $throwable instanceof StaleVersionException => Response::HTTP_PRECONDITION_FAILED,
            // the action is not allowed in the current state of the game
            $throwable instanceof RulesException, $throwable instanceof WorkflowException, $throwable instanceof TransitionException => Response::HTTP_CONFLICT,
            // the payload refers to something that does not exist in this game, or has invalid values
            $throwable instanceof ValidationFailedException, $throwable instanceof NotFoundException, $throwable instanceof GameMismatchException, $throwable instanceof \InvalidArgumentException => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => throw $throwable,
        };

        $type = $throwable instanceof HttpExceptionInterface && ! $throwable instanceof InvalidPayloadException
            ? u(Response::$statusTexts[$status] ?? 'error')->snake()->toString()
            : u(new \ReflectionClass($throwable)->getShortName())->trimSuffix('Exception')->snake()->toString();

        return new ApiProblemException($status, $type, $throwable->getMessage(), match (true) {
            $throwable instanceof InvalidPayloadException => ['violations' => $throwable->getViolations()],
            $throwable instanceof ValidationFailedException => ['violations' => $this->toViolations($throwable->getViolations())],
            default => [],
        }, $throwable);
    }

    /**
     * @return list<array{property_path: string, message: string}>
     */
    private function toViolations(ConstraintViolationListInterface $violations): array
    {
        $list = [];
        foreach ($violations as $violation) {
            $list[] = [
                'property_path' => u($violation->getPropertyPath())->snake()->toString(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $list;
    }
}
