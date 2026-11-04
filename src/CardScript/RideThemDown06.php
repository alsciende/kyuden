<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardImperialFavor;

/**
 * Ride Them Down.
 */
final readonly class RideThemDown06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-ride-them-down';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardImperialFavor::class];
    }
}
