<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\TurnProvinceFacedown;

/**
 * Traveling Philosopher.
 */
final readonly class TravelingPhilosopher30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-traveling-philosopher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [TurnProvinceFacedown::class];
    }
}
