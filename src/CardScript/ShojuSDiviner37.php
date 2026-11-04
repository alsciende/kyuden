<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\PutCardOnDeck;

/**
 * Shoju's Diviner.
 */
final readonly class ShojuSDiviner37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-shoju-s-diviner';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCardOnDeck::class];
    }
}
