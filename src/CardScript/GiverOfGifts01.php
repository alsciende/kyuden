<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveAttachment;

/**
 * Giver of Gifts.
 */
final readonly class GiverOfGifts01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-giver-of-gifts';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class];
    }
}
