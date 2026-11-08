<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * River of the Last Stand.
 */
final readonly class RiverOfTheLastStand26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-river-of-the-last-stand';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
