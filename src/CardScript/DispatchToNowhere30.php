<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Dispatch to Nowhere.
 */
final readonly class DispatchToNowhere30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-dispatch-to-nowhere';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
