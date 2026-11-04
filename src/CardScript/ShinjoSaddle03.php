<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveAttachment;

/**
 * Shinjo Saddle.
 */
final readonly class ShinjoSaddle03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-shinjo-saddle';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class];
    }
}
