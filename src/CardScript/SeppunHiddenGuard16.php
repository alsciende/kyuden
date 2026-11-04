<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\DiscardRandomCardFromHand;

/**
 * Seppun Hidden Guard.
 */
final readonly class SeppunHiddenGuard16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-seppun-hidden-guard';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardRandomCardFromHand::class];
    }
}
