<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ShuffleIntoDeck;

/**
 * Miwaku Kabe.
 */
final readonly class MiwakuKabe03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-miwaku-kabe';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ShuffleIntoDeck::class];
    }
}
