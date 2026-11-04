<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mantis Tenkinja.
 */
final readonly class MantisTenkinja14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-mantis-tenkinja';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
