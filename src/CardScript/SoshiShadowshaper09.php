<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ReturnCardToHand;

/**
 * Soshi Shadowshaper.
 */
final readonly class SoshiShadowshaper09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-soshi-shadowshaper';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnCardToHand::class];
    }
}
