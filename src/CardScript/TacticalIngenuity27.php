<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\RevealCard;

/**
 * Tactical Ingenuity.
 */
final readonly class TacticalIngenuity27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-tactical-ingenuity';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, RevealCard::class];
    }
}
