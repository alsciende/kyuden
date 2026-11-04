<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Dispatch.
 */
final readonly class Dispatch13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-dispatch';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
