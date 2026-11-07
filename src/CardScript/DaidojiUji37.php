<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCards;
use App\Message\PlaceCardUnder;
use App\Message\RemoveFromGame;
use App\Message\ShuffleConflictDeck;

/**
 * Daidoji Uji.
 */
final readonly class DaidojiUji37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-daidoji-uji';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PlaceCardUnder::class, RemoveFromGame::class, ShuffleConflictDeck::class];
    }
}
