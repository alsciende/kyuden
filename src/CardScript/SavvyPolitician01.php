<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Savvy Politician.
 */
final readonly class SavvyPolitician01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-savvy-politician';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
