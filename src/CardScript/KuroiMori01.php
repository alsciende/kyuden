<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\SwitchConflictType;
use App\Message\SwitchRing;

/**
 * Kuroi Mori.
 */
final readonly class KuroiMori01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-kuroi-mori';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchConflictType::class, SwitchRing::class];
    }
}
