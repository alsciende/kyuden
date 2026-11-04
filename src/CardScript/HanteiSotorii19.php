<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hantei Sotorii.
 */
final readonly class HanteiSotorii19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-hantei-sotorii';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
