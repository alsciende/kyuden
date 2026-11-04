<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Subterfuge.
 */
final readonly class Subterfuge41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-subterfuge';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
