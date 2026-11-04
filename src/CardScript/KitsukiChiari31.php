<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\RevealCards;

/**
 * Kitsuki Chiari.
 */
final readonly class KitsukiChiari31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-kitsuki-chiari';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
    }
}
