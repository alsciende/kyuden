<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\DiscardRandomCardFromHand;

/**
 * Paragon of Grace.
 */
final readonly class ParagonOfGrace16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-paragon-of-grace';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardRandomCardFromHand::class];
    }
}
