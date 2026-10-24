<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Sets the starting honor of the target player, once, during setup. Sent by the engine, not by the players.
 */
#[AppAction(public: false)]
final readonly class SetHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player whose starting honor is set.')]
        private string $targetPlayerId,
        #[Description('Starting honor.')]
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
