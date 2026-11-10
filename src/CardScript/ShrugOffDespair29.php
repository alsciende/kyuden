<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveConflict;

/**
 * Shrug Off Despair.
 */
final readonly class ShrugOffDespair29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-shrug-off-despair';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveConflict::class];
    }
}
