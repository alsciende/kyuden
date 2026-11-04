<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCardOnDeck;

/**
 * Hida Sugi.
 */
final readonly class HidaSugi21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-hida-sugi';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
