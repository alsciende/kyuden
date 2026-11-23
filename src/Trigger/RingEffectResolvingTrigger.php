<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\Player;
use App\Enum\Element;

/**
 * The attacking player would resolve the effect of the contested ring, having won the conflict (`resolve_ring_effects`,
 * 3.2.6). Cancelled or replaced ("instead of resolving the ring effect": Leniency, Asako Azunami, Jade Talisman…),
 * nobody resolves it: the replacement is applied with the effect messages.
 */
final readonly class RingEffectResolvingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Element $ring,
        /**
         * The player who would resolve it.
         */
        public Player $player,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'ring_effect_resolving';
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
        return '{player} would resolve the effect of {ring}';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->player, 'ring' => $this->ring];
    }
}
