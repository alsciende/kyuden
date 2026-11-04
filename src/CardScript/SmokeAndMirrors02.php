<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Smoke and Mirrors.
 */
final readonly class SmokeAndMirrors02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-smoke-and-mirrors';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
