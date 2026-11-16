<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCharacterIntoPlay;
use App\Message\SearchDynastyDeck;
use App\Message\ShuffleDynastyDeck;

/**
 * Daughter of War.
 */
final readonly class DaughterOfWar36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-daughter-of-war';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class, SearchDynastyDeck::class, ShuffleDynastyDeck::class];
    }
}
