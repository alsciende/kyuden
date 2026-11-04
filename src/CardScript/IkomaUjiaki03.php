<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * Ikoma Ujiaki.
 */
final readonly class IkomaUjiaki03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-ikoma-ujiaki';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
