<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\ShuffleDynastyDeck;

/**
 * Kaiu Forges.
 */
final readonly class KaiuForges26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-kaiu-forges';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, ShuffleDynastyDeck::class];
    }
}
