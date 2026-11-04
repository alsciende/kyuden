<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Duel to the Death.
 */
final readonly class DuelToTheDeath20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-duel-to-the-death';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
