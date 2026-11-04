<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardRandomCardFromHand;

/**
 * Thunder Guard Elite.
 */
final readonly class ThunderGuardElite30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-thunder-guard-elite';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardRandomCardFromHand::class];
    }
}
