<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * Dark Resurrection.
 */
final readonly class DarkResurrection39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-dark-resurrection';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
