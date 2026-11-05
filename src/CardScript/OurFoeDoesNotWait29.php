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
 * Our Foe Does Not Wait.
 */
final readonly class OurFoeDoesNotWait29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-our-foe-does-not-wait';
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
