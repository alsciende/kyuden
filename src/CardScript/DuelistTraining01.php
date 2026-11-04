<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Duelist Training.
 */
final readonly class DuelistTraining01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-duelist-training';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
