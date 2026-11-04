<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\PutCardOnDeck;

/**
 * Shiro Gisu.
 */
final readonly class ShiroGisu41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-shiro-gisu';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, PutCardOnDeck::class];
    }
}
