<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\PutHoldingIntoProvince;
use App\Message\RevealCards;
use App\Message\ShuffleDynastyDeck;

/**
 * Kaiu Shihobu.
 */
final readonly class KaiuShihobu26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-kaiu-shihobu';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutHoldingIntoProvince::class, RevealCards::class, ShuffleDynastyDeck::class];
    }
}
