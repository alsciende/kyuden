<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Justicar's Approach.
 */
final readonly class JusticarSApproach21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-justicar-s-approach';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
