<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\AddCardToHand;
use App\Message\PutCardOnDeck;

/**
 * Renowned Singer.
 */
final readonly class RenownedSinger36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-renowned-singer';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, PutCardOnDeck::class];
    }
}
