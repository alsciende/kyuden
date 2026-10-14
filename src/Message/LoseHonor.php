<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The target player loses honor, which returns to the general token pool, e.g. to pay a cost or resolve an effect.
 */
#[AppAction]
final readonly class LoseHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $targetPlayerId,
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

    /**
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getHonorLoss(): int
    {
        return $this->honorLoss;
    }
}
