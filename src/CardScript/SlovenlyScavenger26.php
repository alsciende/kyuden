<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ShuffleIntoDeck;

/**
 * Slovenly Scavenger.
 */
final readonly class SlovenlyScavenger26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-slovenly-scavenger';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ShuffleIntoDeck::class];
    }
}
