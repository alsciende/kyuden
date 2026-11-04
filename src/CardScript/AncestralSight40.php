<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;
use App\Message\PutCardOnDeck;

/**
 * Ancestral Sight.
 */
final readonly class AncestralSight40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-ancestral-sight';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class, PutCardOnDeck::class];
    }
}
