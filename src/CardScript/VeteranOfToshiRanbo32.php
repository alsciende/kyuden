<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Veteran of Toshi Ranbo.
 */
final readonly class VeteranOfToshiRanbo32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-veteran-of-toshi-ranbo';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
