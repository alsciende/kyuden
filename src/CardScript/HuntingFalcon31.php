<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCard;

/**
 * Hunting Falcon.
 */
final readonly class HuntingFalcon31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-hunting-falcon';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCard::class];
    }
}
