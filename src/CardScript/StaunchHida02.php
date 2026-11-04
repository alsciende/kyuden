<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Staunch Hida.
 */
final readonly class StaunchHida02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-staunch-hida';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
