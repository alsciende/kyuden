<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ReturnCardToHand;

/**
 * Ancestral Daishō.
 */
final readonly class AncestralDaisho01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-ancestral-daisho';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnCardToHand::class];
    }
}
