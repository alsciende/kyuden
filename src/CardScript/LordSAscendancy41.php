<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Lord's Ascendancy.
 */
final readonly class LordSAscendancy41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-lord-s-ascendancy';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
