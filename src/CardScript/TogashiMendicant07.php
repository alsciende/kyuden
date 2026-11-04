<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\PutCardOnDeck;

/**
 * Togashi Mendicant.
 */
final readonly class TogashiMendicant07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-togashi-mendicant';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCardOnDeck::class];
    }
}
