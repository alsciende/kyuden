<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\RemoveFromGame;

/**
 * Bayushi Kachiko.
 */
final readonly class BayushiKachiko34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-bayushi-kachiko';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RemoveFromGame::class];
    }
}
