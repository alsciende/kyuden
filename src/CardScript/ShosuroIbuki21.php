<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shosuro Ibuki.
 */
final readonly class ShosuroIbuki21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-shosuro-ibuki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
