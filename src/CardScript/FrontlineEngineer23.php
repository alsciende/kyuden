<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\PutHoldingIntoProvince;
use App\Message\ShuffleDynastyDeck;

/**
 * Frontline Engineer.
 */
final readonly class FrontlineEngineer23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-frontline-engineer';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutHoldingIntoProvince::class, ShuffleDynastyDeck::class];
    }
}
