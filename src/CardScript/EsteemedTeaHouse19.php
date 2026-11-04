<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ReturnCardToHand;

/**
 * Esteemed Tea House.
 */
final readonly class EsteemedTeaHouse19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-esteemed-tea-house';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnCardToHand::class];
    }
}
