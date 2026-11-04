<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCardOnDeck;
use App\Message\PutCharacterIntoPlay;

/**
 * Spectral Visitation.
 */
final readonly class SpectralVisitation30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-spectral-visitation';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class, PutCharacterIntoPlay::class];
    }
}
