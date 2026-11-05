<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCards;
use App\Message\PutCardIntoProvince;
use App\Message\ShuffleDynastyDeck;

/**
 * Walking the Way.
 */
final readonly class WalkingTheWay08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-walking-the-way';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCardIntoProvince::class, ShuffleDynastyDeck::class];
    }
}
