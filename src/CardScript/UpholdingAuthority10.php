<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\RevealCards;

/**
 * Upholding Authority.
 */
final readonly class UpholdingAuthority10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-upholding-authority';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
    }
}
