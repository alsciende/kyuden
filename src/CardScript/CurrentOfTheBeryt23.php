<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Current of the Beryt.
 */
final readonly class CurrentOfTheBeryt23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-current-of-the-beryt';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
