<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\Player;
use App\Enum\Element;

/**
 * A ring claimed by a player would be returned to the unclaimed ring pool: by an effect (ReturnRing), or in the fate
 * phase (`rings_returned`, 5.7). Cancelled (Wholeness of the World: "it remains claimed instead", CancelImminentTrigger
 * naming the ring), it stays claimed.
 */
final readonly class RingReturningTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Element $ring,
        /**
         * The player who has claimed it.
         */
        public Player $player,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'ring_returning';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['ring' => $this->ring->value, 'player' => $this->player->getId()];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{ring} would be returned to the unclaimed ring pool';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['ring' => $this->ring];
    }
}
