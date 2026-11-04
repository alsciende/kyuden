<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\SwitchRing;

/**
 * Elemental Fury.
 */
final readonly class ElementalFury01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-elemental-fury';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchRing::class];
    }
}
