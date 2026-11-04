<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seven Stings Keep.
 */
final readonly class SevenStingsKeep30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-seven-stings-keep';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
