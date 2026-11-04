<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardRandomCardFromHand;

/**
 * Spies at Court.
 */
final readonly class SpiesAtCourt01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-spies-at-court';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardRandomCardFromHand::class];
    }
}
