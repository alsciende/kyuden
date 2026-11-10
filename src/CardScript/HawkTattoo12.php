<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\GiveActionOpportunity;

/**
 * Hawk Tattoo.
 */
final readonly class HawkTattoo12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-hawk-tattoo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [GiveActionOpportunity::class];
    }
}
