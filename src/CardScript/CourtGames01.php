<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Court Games.
 */
final readonly class CourtGames01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-court-games';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
