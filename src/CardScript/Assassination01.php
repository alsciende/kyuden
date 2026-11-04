<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Assassination.
 */
final readonly class Assassination01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-assassination';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
