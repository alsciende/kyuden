<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Smoke.
 */
final readonly class Smoke11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-smoke';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
