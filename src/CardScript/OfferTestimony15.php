<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\RevealCard;

/**
 * Offer Testimony.
 */
final readonly class OfferTestimony15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-offer-testimony';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCard::class];
    }
}
