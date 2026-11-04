<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * City of the Rich Frog.
 */
final readonly class CityOfTheRichFrog29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-city-of-the-rich-frog';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
