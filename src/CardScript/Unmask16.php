<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Unmask.
 */
final readonly class Unmask16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-unmask';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
