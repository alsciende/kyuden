<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * From the Shadows.
 */
final readonly class FromTheShadows15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-from-the-shadows';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
