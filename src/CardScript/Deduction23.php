<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Deduction.
 */
final readonly class Deduction23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-deduction';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
