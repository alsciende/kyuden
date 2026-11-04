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
 * Ancient Master.
 */
final readonly class AncientMaster09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-ancient-master';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, RevealCard::class, ShuffleConflictDeck::class];
    }
}
