<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\ShuffleConflictDeck;

/**
 * Third Whisker Sneak.
 */
final readonly class ThirdWhiskerSneak31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-third-whisker-sneak';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, ShuffleConflictDeck::class];
    }
}
