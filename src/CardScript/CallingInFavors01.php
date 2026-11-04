<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveAttachment;

/**
 * Calling in Favors.
 */
final readonly class CallingInFavors01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-calling-in-favors';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class];
    }
}
