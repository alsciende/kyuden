<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * For Shame!.
 */
final readonly class ForShame01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-for-shame';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
