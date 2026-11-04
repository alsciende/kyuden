<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Repentant Legion.
 */
final readonly class RepentantLegion40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-repentant-legion';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
