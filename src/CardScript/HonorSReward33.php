<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Honor's Reward.
 */
final readonly class HonorSReward33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-honor-s-reward';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
