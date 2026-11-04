<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;

/**
 * Master Tactician.
 */
final readonly class MasterTactician31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-master-tactician';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
