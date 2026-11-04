<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Feast or Famine.
 */
final readonly class FeastOrFamine04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-feast-or-famine';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
