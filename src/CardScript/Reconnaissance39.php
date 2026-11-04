<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCard;

/**
 * Reconnaissance.
 */
final readonly class Reconnaissance39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-reconnaissance';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCard::class];
    }
}
