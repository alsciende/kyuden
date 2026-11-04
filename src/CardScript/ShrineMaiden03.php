<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\RevealCards;

/**
 * Shrine Maiden.
 */
final readonly class ShrineMaiden03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-shrine-maiden';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, RevealCards::class];
    }
}
