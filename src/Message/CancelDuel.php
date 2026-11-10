<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * Cancels the duel being resolved, at any step (Stay Your Hand): it ends without result, the secret bids of its honor
 * dials forgotten, and the action window in which it was initiated goes on.
 *
 * Any player of the game can send it: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class CancelDuel implements PlayerActionInterface
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
