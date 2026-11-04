<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Mirumoto Raitsugu.
 */
final readonly class MirumotoRaitsugu01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-mirumoto-raitsugu';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
