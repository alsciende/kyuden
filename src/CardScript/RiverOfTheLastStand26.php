<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardRandomCardFromHand;

/**
 * River of the Last Stand.
 */
final readonly class RiverOfTheLastStand26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-river-of-the-last-stand';
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
