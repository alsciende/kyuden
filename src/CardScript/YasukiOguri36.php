<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yasuki Oguri.
 */
final readonly class YasukiOguri36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-yasuki-oguri';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
