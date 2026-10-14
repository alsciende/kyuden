<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\Api\StaleVersionException;
use App\Message\ActionInterface;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Repository\GameRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Rejects a message sent with an expected version (ExpectedVersionStamp) once the game has changed, after the game is
 * locked and before its handling.
 */
readonly class GameVersionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GameRepository $gameRepository,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $stamp = $envelope->last(ExpectedVersionStamp::class);

        if ($message instanceof ActionInterface && $stamp instanceof ExpectedVersionStamp) {
            $version = $this->gameRepository->get($message->getGameId())->getVersion();
            if ($version !== $stamp->version) {
                throw new StaleVersionException($stamp->version, $version);
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
