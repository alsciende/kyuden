<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\FillProvince;

/**
 * Ride at Dawn.
 */
final readonly class RideAtDawn32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-ride-at-dawn';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
