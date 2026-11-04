<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Foothills Keep.
 */
final readonly class FoothillsKeep33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-foothills-keep';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
