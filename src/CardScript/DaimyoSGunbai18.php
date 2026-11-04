<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;
use App\Message\RevealCard;

/**
 * Daimyō's Gunbai.
 */
final readonly class DaimyoSGunbai18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-daimyo-s-gunbai';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class, RevealCard::class];
    }
}
