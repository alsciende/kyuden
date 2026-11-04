<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * Leniency.
 */
final readonly class Leniency16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-leniency';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
