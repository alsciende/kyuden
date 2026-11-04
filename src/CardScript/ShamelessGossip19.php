<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shameless Gossip.
 */
final readonly class ShamelessGossip19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-shameless-gossip';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
