<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddAttackedProvince;

/**
 * A War on Two Fronts.
 */
final readonly class AWarOnTwoFronts41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-a-war-on-two-fronts';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddAttackedProvince::class];
    }
}
