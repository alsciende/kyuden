<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sudden Tempest.
 */
final readonly class SuddenTempest34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-sudden-tempest';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
