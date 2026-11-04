<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fū Sui Disciple.
 */
final readonly class FuSuiDisciple09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-fu-sui-disciple';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
