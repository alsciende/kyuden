<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\Element;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Removes fate from a ring and returns it to the general token pool (e.g. "remove 1 fate from each unclaimed ring":
 * Written in the Stars). Fate taken into a fate pool comes from a ring with move_fate.
 */
#[AppAction(special: true)]
final readonly class RemoveFateFromRing implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Ring the fate is removed from.')]
        private Element $ring,
        #[Description('Fate removed.')]
        #[Assert\Positive]
        private int $amount,
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

    public function getRing(): Element
    {
        return $this->ring;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}
