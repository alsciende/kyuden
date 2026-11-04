<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\RevealCards;
use App\Message\ShuffleConflictDeck;

/**
 * The Perfect Gift.
 */
final readonly class ThePerfectGift01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-the-perfect-gift';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, RevealCards::class, ShuffleConflictDeck::class];
    }
}
