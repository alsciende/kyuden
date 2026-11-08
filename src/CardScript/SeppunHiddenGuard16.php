<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seppun Hidden Guard.
 */
final readonly class SeppunHiddenGuard16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-seppun-hidden-guard';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
