<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\Api\StaleVersionException;
use App\Message\ActionInterface;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Repository\GameRepository;
use App\Service\ActionMessageRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Rejects a message sent with an expected version (ExpectedVersionStamp) once the game has changed, after the game is
 * locked and before its handling. A message that does not depend on what the other players did meanwhile
 * (AppAction::$independent) is handled whatever the version expected: its handler checks what it needs.
 */
readonly class GameVersionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private ActionMessageRegistry $actionMessageRegistry,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $stamp = $envelope->last(ExpectedVersionStamp::class);

        if ($message instanceof ActionInterface && $stamp instanceof ExpectedVersionStamp && ! $this->actionMessageRegistry->getAttribute($message::class)->independent) {
            $version = $this->gameRepository->get($message->getGameId())->getVersion();
            if ($version !== $stamp->version) {
                throw new StaleVersionException($stamp->version, $version);
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
