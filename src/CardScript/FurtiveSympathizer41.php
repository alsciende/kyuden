<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Furtive Sympathizer.
 */
final readonly class FurtiveSympathizer41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-furtive-sympathizer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
