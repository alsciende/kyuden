<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shinjo Outrider.
 */
final readonly class ShinjoOutrider01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shinjo-outrider';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
