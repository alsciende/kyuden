<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\RevealCard;
use App\Message\ShuffleConflictDeck;

/**
 * Shiba Sophist.
 */
final readonly class ShibaSophist19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-shiba-sophist';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, RevealCard::class, ShuffleConflictDeck::class];
    }
}
