<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Disparaging Challenge.
 */
final readonly class DisparagingChallenge38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-disparaging-challenge';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
