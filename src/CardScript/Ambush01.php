<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * Ambush.
 */
final readonly class Ambush01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-ambush';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
