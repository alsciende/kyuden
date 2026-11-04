<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Abandoning Honor.
 */
final readonly class AbandoningHonor11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-abandoning-honor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
