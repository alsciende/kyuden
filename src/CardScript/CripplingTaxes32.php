<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Crippling Taxes.
 */
final readonly class CripplingTaxes32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-crippling-taxes';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
