<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Bustling Academy.
 */
final readonly class BustlingAcademy13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-bustling-academy';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
