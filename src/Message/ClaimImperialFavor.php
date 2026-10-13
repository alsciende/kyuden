<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Enum\ConflictType;

/**
 * The player who won the glory count claims the Imperial Favor and sets it to its military or political side
 * (3.4.2).
 */
#[AppAction]
final readonly class ClaimImperialFavor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private ConflictType $side,
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

    public function getSide(): ConflictType
    {
        return $this->side;
    }
}
