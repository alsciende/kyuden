<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * A Season of War.
 */
final readonly class ASeasonOfWar29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-a-season-of-war';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
