<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCardIntoProvince;

/**
 * Mountaintop Statuary.
 */
final readonly class MountaintopStatuary05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-mountaintop-statuary';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action, AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardIntoProvince::class];
    }
}
