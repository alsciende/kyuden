<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * A player involved in the duel ends it once its results are applied (D.9): the action window in which it was
 * initiated goes on.
 */
#[AppAction]
final readonly class EndDuel implements PlayerActionInterface
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
