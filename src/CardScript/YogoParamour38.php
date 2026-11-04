<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yogo Paramour.
 */
final readonly class YogoParamour38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-yogo-paramour';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
