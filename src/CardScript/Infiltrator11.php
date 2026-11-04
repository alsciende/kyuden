<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;

/**
 * Infiltrator.
 */
final readonly class Infiltrator11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-infiltrator';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
