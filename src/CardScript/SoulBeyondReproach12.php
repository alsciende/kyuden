<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Soul Beyond Reproach.
 */
final readonly class SoulBeyondReproach12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-soul-beyond-reproach';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
