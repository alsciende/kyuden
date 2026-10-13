<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The player loses honor, which returns to the general token pool, e.g. to pay a cost or resolve an effect.
 */
#[AppAction]
final readonly class LoseHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private int $honorLoss,
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

    public function getHonorLoss(): int
    {
        return $this->honorLoss;
    }
}
