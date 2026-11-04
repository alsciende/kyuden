<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;

/**
 * Isawa Tadaka.
 */
final readonly class IsawaTadaka20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-isawa-tadaka';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
