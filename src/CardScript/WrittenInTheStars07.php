<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceFateOnRing;
use App\Message\RemoveFateFromRing;

/**
 * Written in the Stars.
 */
final readonly class WrittenInTheStars07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-written-in-the-stars';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceFateOnRing::class, RemoveFateFromRing::class];
    }
}
