<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\DiscardRandomCardFromHand;

/**
 * Kuni Purifier.
 */
final readonly class KuniPurifier32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-kuni-purifier';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardRandomCardFromHand::class];
    }
}
