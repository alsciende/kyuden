<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\RevealCards;

/**
 * Inquisitorial Initiate.
 */
final readonly class InquisitorialInitiate41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-inquisitorial-initiate';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
    }
}
