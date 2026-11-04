<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\RevealCards;
use App\Message\ShuffleConflictDeck;

/**
 * Ki Alignment.
 */
final readonly class KiAlignment38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-ki-alignment';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, RevealCards::class, ShuffleConflictDeck::class];
    }
}
