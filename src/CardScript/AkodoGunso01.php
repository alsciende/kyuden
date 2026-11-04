<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Akodo Gunsō.
 */
final readonly class AkodoGunso01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-akodo-gunso';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
