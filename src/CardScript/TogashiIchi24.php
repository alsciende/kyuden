<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\BreakProvince;

/**
 * Togashi Ichi.
 */
final readonly class TogashiIchi24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-togashi-ichi';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [BreakProvince::class];
    }
}
