<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * Passes in the interrupt window open, without interrupting what is about to happen: once every player asked has
 * passed (an interrupt initiated meanwhile cancelling the passes), the window moves on to its next stage, or closes
 * and what waited is done. A player who has passed may still initiate an interrupt in the same window.
 */
#[AppAction]
final readonly class PassInterrupt implements PlayerActionInterface
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
