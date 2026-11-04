<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Windswept Yurt.
 */
final readonly class WindsweptYurt02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-windswept-yurt';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
