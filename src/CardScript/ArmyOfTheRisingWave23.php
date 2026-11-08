<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceFateOnRing;

/**
 * Army of the Rising Wave.
 */
final readonly class ArmyOfTheRisingWave23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-army-of-the-rising-wave';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceFateOnRing::class];
    }
}
