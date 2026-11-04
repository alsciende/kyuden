<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\ShuffleConflictDeck;

/**
 * Illustrious Forge.
 */
final readonly class IllustriousForge16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-illustrious-forge';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, ShuffleConflictDeck::class];
    }
}
