<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;

/**
 * Court of Justice.
 */
final readonly class CourtOfJustice21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-court-of-justice';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
