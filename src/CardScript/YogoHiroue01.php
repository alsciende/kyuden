<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yogo Hiroue.
 */
final readonly class YogoHiroue01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-yogo-hiroue';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
