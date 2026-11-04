<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Courteous Scheming.
 */
final readonly class CourteousScheming18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-courteous-scheming';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
