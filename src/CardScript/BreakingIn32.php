<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCards;
use App\Message\ShuffleDynastyDeck;

/**
 * Breaking In.
 */
final readonly class BreakingIn32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-breaking-in';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, ShuffleDynastyDeck::class];
    }
}
