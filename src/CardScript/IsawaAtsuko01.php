<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Isawa Atsuko.
 */
final readonly class IsawaAtsuko01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-isawa-atsuko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
