<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Ikoma Tsanuri.
 */
final readonly class IkomaTsanuri32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-ikoma-tsanuri';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
