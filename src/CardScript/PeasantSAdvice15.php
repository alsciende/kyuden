<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCard;
use App\Message\ShuffleIntoDeck;

/**
 * Peasant's Advice.
 */
final readonly class PeasantSAdvice15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-peasant-s-advice';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCard::class, ShuffleIntoDeck::class];
    }
}
