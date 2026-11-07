<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The player ends a game their opponent no longer plays: the opponent has abandoned it, the player wins. Sent by the
 * platform (AbandonmentClaimer) once the game has waited for the opponent long enough, which depends on the time and
 * on whether the opponent still reads the game: not a fact the replay of the events could check again.
 */
#[AppAction(public: false)]
final readonly class ClaimAbandonment implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }
}
