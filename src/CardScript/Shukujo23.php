<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\SwitchConflictType;

/**
 * Shukujo.
 */
final readonly class Shukujo23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-shukujo';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchConflictType::class];
    }
}
