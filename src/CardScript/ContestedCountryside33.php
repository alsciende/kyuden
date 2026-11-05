<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;

/**
 * Contested Countryside.
 */
final readonly class ContestedCountryside33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-contested-countryside';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
