<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Message\ActionInterface;
use App\Notification\GameUpdatedNotification;
use App\Notifier\NotifierInterface;
use App\Repository\GameRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Notifies the players that the game has changed, once the transaction is committed: a player reading the game on
 * notification sees the new version.
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
            $this->notifier->notify(new GameUpdatedNotification($this->gameRepository->get($message->getGameId())));
        }

        return $envelope;
    }
}
