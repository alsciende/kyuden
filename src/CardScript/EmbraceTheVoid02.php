<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Embrace the Void.
 */
final readonly class EmbraceTheVoid02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-embrace-the-void';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
