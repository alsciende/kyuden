<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The platform ends a game every player has left: none of them has read it for long, and it has not changed since. Every
 * player is eliminated, nobody wins. Sent by the platform (AbandonedGamesEnder, a task of the worker): how long the game
 * has been left depends on the time, not a fact the replay of the events could check again.
 */
#[AppAction(public: false)]
final readonly class EndAbandonedGame implements ActionInterface
{
    public function __construct(
        private string $gameId,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }
}
