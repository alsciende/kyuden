<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\GrantConflictOpportunity;

/**
 * Shori.
 */
final readonly class Shori31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-shori';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [GrantConflictOpportunity::class];
    }
}
