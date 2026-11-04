<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Doji Fumiki.
 */
final readonly class DojiFumiki15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-doji-fumiki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
