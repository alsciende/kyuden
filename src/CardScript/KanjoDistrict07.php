<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kanjo District.
 */
final readonly class KanjoDistrict07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-kanjo-district';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
