<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

#[AppAction]
final readonly class SetHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $targetPlayerId,
        private int $honor,
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

    public function getHonor(): int
    {
        return $this->honor;
    }
}
