<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\FillProvince;

/**
 * Honored Veterans.
 */
final readonly class HonoredVeterans33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-honored-veterans';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
