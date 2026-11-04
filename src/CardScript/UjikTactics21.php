<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ujik Tactics.
 */
final readonly class UjikTactics21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-ujik-tactics';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
