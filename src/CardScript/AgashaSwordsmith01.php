<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\RevealCard;
use App\Message\ShuffleConflictDeck;

/**
 * Agasha Swordsmith.
 */
final readonly class AgashaSwordsmith01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-agasha-swordsmith';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, RevealCard::class, ShuffleConflictDeck::class];
    }
}
