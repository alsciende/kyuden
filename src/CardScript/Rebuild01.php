<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutHoldingIntoProvince;
use App\Message\ShuffleIntoDeck;

/**
 * Rebuild.
 */
final readonly class Rebuild01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-rebuild';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutHoldingIntoProvince::class, ShuffleIntoDeck::class];
    }
}
