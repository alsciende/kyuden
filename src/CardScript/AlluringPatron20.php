<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Alluring Patron.
 */
final readonly class AlluringPatron20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-alluring-patron';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
