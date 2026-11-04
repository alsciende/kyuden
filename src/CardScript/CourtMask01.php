<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ReturnCardToHand;

/**
 * Court Mask.
 */
final readonly class CourtMask01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-court-mask';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnCardToHand::class];
    }
}
