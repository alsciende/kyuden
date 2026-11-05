<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Butcher of the Fallen.
 */
final readonly class ButcherOfTheFallen30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-butcher-of-the-fallen';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
