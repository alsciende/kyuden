<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCardOnDeck;

/**
 * Exposed Courtyard.
 */
final readonly class ExposedCourtyard34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-exposed-courtyard';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
