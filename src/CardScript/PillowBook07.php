<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\RevealCards;

/**
 * Pillow Book.
 */
final readonly class PillowBook07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-pillow-book';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
    }
}
