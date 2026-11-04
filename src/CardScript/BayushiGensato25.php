<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Bayushi Gensato.
 */
final readonly class BayushiGensato25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-bayushi-gensato';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
