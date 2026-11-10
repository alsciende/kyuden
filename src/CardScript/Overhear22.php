<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCardOnDeck;
use App\Message\RevealCards;

/**
 * Overhear.
 */
final readonly class Overhear22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-overhear';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class, RevealCards::class];
    }
}
