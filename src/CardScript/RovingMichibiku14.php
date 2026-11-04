<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ClaimRing;

/**
 * Roving Michibiku.
 */
final readonly class RovingMichibiku14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-roving-michibiku';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ClaimRing::class];
    }
}
