<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sententious Poet.
 */
final readonly class SententiousPoet19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-sententious-poet';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
