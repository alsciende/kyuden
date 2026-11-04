<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Magistrate of Reason.
 */
final readonly class MagistrateOfReason19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-magistrate-of-reason';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
