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
 * Emperor's Summons.
 */
final readonly class EmperorSSummons22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-emperor-s-summons';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCardIntoProvince::class, ShuffleDynastyDeck::class];
    }
}
