<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yasuki Oguri.
 */
final readonly class YasukiOguri15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-yasuki-oguri';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
