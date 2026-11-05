<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Shadow Stalker.
 */
final readonly class ShadowStalker32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-shadow-stalker';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
