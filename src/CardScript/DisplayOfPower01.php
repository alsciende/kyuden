<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ClaimRing;

/**
 * Display of Power.
 */
final readonly class DisplayOfPower01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-display-of-power';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ClaimRing::class];
    }
}
