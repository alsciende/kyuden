<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Miya Satoshi.
 */
final readonly class MiyaSatoshi07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-miya-satoshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
