<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;

/**
 * Kyūden Hida.
 */
final readonly class KyudenHida26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-kyuden-hida';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
