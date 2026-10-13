<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The bid on the honor dial of the player changes, e.g. to resolve a reaction to the honor dials being revealed.
 */
#[AppAction]
final readonly class SetHonorBid implements PlayerActionInterface
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
