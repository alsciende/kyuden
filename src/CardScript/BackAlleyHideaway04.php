<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceCardUnder;

/**
 * Back-Alley Hideaway.
 */
final readonly class BackAlleyHideaway04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-back-alley-hideaway';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action, AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceCardUnder::class];
    }
}
