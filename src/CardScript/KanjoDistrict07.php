<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardImperialFavor;

/**
 * Kanjo District.
 */
final readonly class KanjoDistrict07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-kanjo-district';
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
