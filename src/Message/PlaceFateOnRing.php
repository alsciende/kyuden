<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\Element;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Places fate from the general token pool on a ring (e.g. "place 1 fate on each unclaimed ring": Written in the Stars,
 * Fuchi Mura). Fate taken from a fate pool goes to a ring with move_fate.
 */
#[AppAction(special: true)]
final readonly class PlaceFateOnRing implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Ring the fate is placed on.')]
        private Element $ring,
        #[Description('Fate placed.')]
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
