<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\GrantConflictOpportunity;

/**
 * Hisu Mori Toride.
 */
final readonly class HisuMoriToride09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-hisu-mori-toride';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [GrantConflictOpportunity::class];
    }
}
