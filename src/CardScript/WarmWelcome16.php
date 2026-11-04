<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCardOnDeck;

/**
 * Warm Welcome.
 */
final readonly class WarmWelcome16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-warm-welcome';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
