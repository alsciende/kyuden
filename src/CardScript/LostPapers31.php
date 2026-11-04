<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Lost Papers.
 */
final readonly class LostPapers31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-lost-papers';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
