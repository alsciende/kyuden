<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * The Fires of Justice.
 */
final readonly class TheFiresOfJustice16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-the-fires-of-justice';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
