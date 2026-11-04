<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Cleanse the Empire.
 */
final readonly class CleanseTheEmpire41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-cleanse-the-empire';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
