<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shinjo Haruko.
 */
final readonly class ShinjoHaruko18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-shinjo-haruko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
