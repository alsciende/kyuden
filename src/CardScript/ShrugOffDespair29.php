<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shrug Off Despair.
 */
final readonly class ShrugOffDespair29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-shrug-off-despair';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
