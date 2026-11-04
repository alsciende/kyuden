<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\FillProvince;
use App\Message\LookAtCards;
use App\Message\ShuffleDynastyDeck;

/**
 * The Wealth of the Crane.
 */
final readonly class TheWealthOfTheCrane31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-the-wealth-of-the-crane';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class, LookAtCards::class, ShuffleDynastyDeck::class];
    }
}
