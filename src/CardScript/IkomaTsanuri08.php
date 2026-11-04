<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ikoma Tsanuri.
 */
final readonly class IkomaTsanuri08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-ikoma-tsanuri';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
