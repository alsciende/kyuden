<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\SwitchProvinceCards;

/**
 * Borderlands Fortifications.
 */
final readonly class BorderlandsFortifications01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-borderlands-fortifications';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchProvinceCards::class];
    }
}
