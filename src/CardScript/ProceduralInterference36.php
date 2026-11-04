<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Procedural Interference.
 */
final readonly class ProceduralInterference36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-procedural-interference';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
