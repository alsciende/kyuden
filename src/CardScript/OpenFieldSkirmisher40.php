<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Open Field Skirmisher.
 */
final readonly class OpenFieldSkirmisher40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-open-field-skirmisher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
