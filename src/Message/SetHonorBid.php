<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The bid on the honor dial of the target player changes, e.g. to resolve a reaction to the honor dials being revealed.
 */
#[AppAction]
final readonly class SetHonorBid implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player whose honor dial changes.')]
        private string $targetPlayerId,
        #[Description('New number on the honor dial.')]
        #[Assert\PositiveOrZero]
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

    /**
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getBid(): int
    {
        return $this->bid;
    }
}
