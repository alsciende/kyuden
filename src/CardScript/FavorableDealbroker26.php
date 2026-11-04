<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\PutCharacterIntoPlay;
use App\Message\ShuffleDynastyDeck;

/**
 * Favorable Dealbroker.
 */
final readonly class FavorableDealbroker26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-favorable-dealbroker';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCharacterIntoPlay::class, ShuffleDynastyDeck::class];
    }
}
