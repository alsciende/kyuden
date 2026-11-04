<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * Discards the Imperial Favor, which returns to its unclaimed state, e.g. to pay the cost of an ability (Kanjo
 * District, Kakita Yoshi, Fair Accord).
 */
#[AppAction(special: true)]
final readonly class DiscardImperialFavor implements PlayerActionInterface
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
