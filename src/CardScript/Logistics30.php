<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Logistics.
 */
final readonly class Logistics30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-logistics';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
