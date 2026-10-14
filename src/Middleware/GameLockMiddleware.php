<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Message\ActionInterface;
use App\Repository\GameRepository;
use Doctrine\DBAL\LockMode;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Locks the game until the end of the transaction, before its handling: the actions on a game, including the automatic
 * transitions they lead to, are handled one at a time.
 *
 * A game already loaded is locked but not reloaded: load nothing of the game before dispatching its actions.
 */
readonly class GameLockMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GameRepository $gameRepository,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        // the game does not exist yet when it is created
        if ($message instanceof ActionInterface) {
            $this->gameRepository->find($message->getGameId(), LockMode::PESSIMISTIC_WRITE);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
