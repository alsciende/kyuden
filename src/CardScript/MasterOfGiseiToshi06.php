<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Master of Gisei Toshi.
 */
final readonly class MasterOfGiseiToshi06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-master-of-gisei-toshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
