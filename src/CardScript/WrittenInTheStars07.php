<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Written in the Stars.
 */
final readonly class WrittenInTheStars07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-written-in-the-stars';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
