<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\RevealCards;

/**
 * Daidoji Harrier.
 */
final readonly class DaidojiHarrier24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-daidoji-harrier';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
    }
}
