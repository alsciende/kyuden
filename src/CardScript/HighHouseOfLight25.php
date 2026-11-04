<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * High House of Light.
 */
final readonly class HighHouseOfLight25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-high-house-of-light';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
