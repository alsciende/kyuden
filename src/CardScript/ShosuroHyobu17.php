<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shosuro Hyobu.
 */
final readonly class ShosuroHyobu17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-shosuro-hyobu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
