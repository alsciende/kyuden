<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardImperialFavor;

/**
 * Sake House Confidant.
 */
final readonly class SakeHouseConfidant07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-sake-house-confidant';
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
