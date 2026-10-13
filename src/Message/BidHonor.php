<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The player selects a number on their honor dial (honor bid of the draw phase).
 */
#[AppAction]
final readonly class BidHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private int $bid,
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

    public function getBid(): int
    {
        return $this->bid;
    }
}
