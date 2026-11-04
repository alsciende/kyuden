<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;

/**
 * Blade of 10,000 Battles.
 */
final readonly class BladeOf10000Battles19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-blade-of-10-000-battles';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class];
    }
}
