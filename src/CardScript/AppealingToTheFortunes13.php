<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * Appealing to the Fortunes.
 */
final readonly class AppealingToTheFortunes13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-appealing-to-the-fortunes';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
