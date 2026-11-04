<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCardOnDeck;

/**
 * Chrysanthemum Steward.
 */
final readonly class ChrysanthemumSteward24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-chrysanthemum-steward';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
