<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ReturnRing;

/**
 * Henshin Mysteries.
 */
final readonly class HenshinMysteries23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-henshin-mysteries';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnRing::class];
    }
}
