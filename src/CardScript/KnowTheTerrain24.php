<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\SwitchProvinceCards;

/**
 * Know the Terrain.
 */
final readonly class KnowTheTerrain24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-know-the-terrain';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchProvinceCards::class];
    }
}
