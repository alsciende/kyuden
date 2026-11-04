<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hida Guardian.
 */
final readonly class HidaGuardian01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-hida-guardian';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
