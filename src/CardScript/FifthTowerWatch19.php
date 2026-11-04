<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fifth Tower Watch.
 */
final readonly class FifthTowerWatch19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-fifth-tower-watch';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
