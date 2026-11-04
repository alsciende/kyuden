<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Disciple of Shinsei.
 */
final readonly class DiscipleOfShinsei30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-disciple-of-shinsei';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
