<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mountain's Anvil Castle.
 */
final readonly class MountainSAnvilCastle01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-mountain-s-anvil-castle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
