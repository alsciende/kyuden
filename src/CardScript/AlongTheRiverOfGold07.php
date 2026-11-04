<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Along the River of Gold.
 */
final readonly class AlongTheRiverOfGold07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-along-the-river-of-gold';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
