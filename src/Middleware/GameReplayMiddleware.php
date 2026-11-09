<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\Rules\ReplayReadOnlyException;
use App\Message\ActionInterface;
use App\Middleware\Stamp\ReplayStamp;
use App\Repository\GameRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Rejects every action on a replay (Service/Replay/GameReplayer) but the events it replays (ReplayStamp), before its
 * handling: whatever sends it (a player, a bot, the platform), a replay is read only.
 */
readonly class GameReplayMiddleware implements MiddlewareInterface
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
        if (
            $message instanceof ActionInterface
            && $envelope->last(ReplayStamp::class) === null
            && $this->gameRepository->find($message->getGameId())?->isReplay() === true
        ) {
            throw new ReplayReadOnlyException();
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
