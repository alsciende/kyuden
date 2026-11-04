<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCardOnDeck;

/**
 * In Service to My Lord.
 */
final readonly class InServiceToMyLord24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-in-service-to-my-lord';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
