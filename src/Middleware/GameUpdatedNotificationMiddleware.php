<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Message\ActionInterface;
use App\Message\JoinGame;
use App\Notification\GameStartedNotification;
use App\Notification\GameUpdatedNotification;
use App\Notifier\NotifierInterface;
use App\Repository\GameRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Notifies the players that the game has changed, once the transaction is committed: a player reading the game on
 * notification sees the new version. Once the last player has joined a game, it has started: every player is also told
 * on their topic, so that a user matched by the queue finds their game.
 */
readonly class GameUpdatedNotificationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private NotifierInterface $notifier,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        $message = $envelope->getMessage();
        if ($message instanceof ActionInterface) {
            $game = $this->gameRepository->get($message->getGameId());
            $this->notifier->notify(new GameUpdatedNotification($game));
            if ($message instanceof JoinGame && $game->getCurrentPlace() !== 'join_game') {
                $this->notifier->notify(new GameStartedNotification($game));
            }
        }

        return $envelope;
    }
}
