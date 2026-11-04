<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Karmic Twist.
 */
final readonly class KarmicTwist08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-karmic-twist';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
