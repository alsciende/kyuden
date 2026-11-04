<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ShuffleIntoDeck;

/**
 * Inspired Visionary.
 */
final readonly class InspiredVisionary30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-inspired-visionary';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ShuffleIntoDeck::class];
    }
}
