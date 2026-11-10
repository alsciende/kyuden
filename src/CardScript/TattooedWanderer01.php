<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PlayCharacterAsAttachment;

/**
 * Tattooed Wanderer.
 */
final readonly class TattooedWanderer01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-tattooed-wanderer';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlayCharacterAsAttachment::class];
    }
}
