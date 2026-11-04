<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCardOnDeck;

/**
 * Keen Warrior.
 */
final readonly class KeenWarrior36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-keen-warrior';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
