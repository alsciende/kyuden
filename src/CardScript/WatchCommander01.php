<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Watch Commander.
 */
final readonly class WatchCommander01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-watch-commander';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
