<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Callow Delegate.
 */
final readonly class CallowDelegate13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-callow-delegate';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
