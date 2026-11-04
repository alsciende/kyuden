<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;
use App\Message\ShuffleIntoDeck;

/**
 * Cycle of Rebirth.
 */
final readonly class CycleOfRebirth29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-cycle-of-rebirth';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class, ShuffleIntoDeck::class];
    }
}
