<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Austere Exemplar.
 */
final readonly class AustereExemplar25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-austere-exemplar';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
