<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceCardUnder;

/**
 * Under Siege.
 */
final readonly class UnderSiege32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-under-siege';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceCardUnder::class];
    }
}
