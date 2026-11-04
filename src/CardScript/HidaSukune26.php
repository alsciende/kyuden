<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hida Sukune.
 */
final readonly class HidaSukune26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-hida-sukune';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
