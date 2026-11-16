<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\RevealCard;
use App\Message\SearchConflictDeck;
use App\Message\ShuffleConflictDeck;

/**
 * Shosuro Hametsu.
 */
final readonly class ShosuroHametsu16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-shosuro-hametsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, RevealCard::class, SearchConflictDeck::class, ShuffleConflictDeck::class];
    }
}
