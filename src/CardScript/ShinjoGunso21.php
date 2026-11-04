<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\PutCharacterIntoPlay;

/**
 * Shinjo Gunsō.
 */
final readonly class ShinjoGunso21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-shinjo-gunso';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCharacterIntoPlay::class];
    }
}
