<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveAttachment;

/**
 * Iuchi Rimei.
 */
final readonly class IuchiRimei25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-iuchi-rimei';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class];
    }
}
