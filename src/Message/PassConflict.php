<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The active player passes a conflict opportunity (3.2): they forfeit it, whatever its type.
 */
#[AppAction]
final readonly class PassConflict implements PlayerActionInterface
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
