<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\AddCardToHand;

/**
 * Sharpened Tsuruhashi.
 */
final readonly class SharpenedTsuruhashi22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-sharpened-tsuruhashi';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class];
    }
}
