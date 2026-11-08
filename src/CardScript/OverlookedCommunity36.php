<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardStatusToken;
use App\Message\ReturnRing;

/**
 * Overlooked Community.
 */
final readonly class OverlookedCommunity36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-overlooked-community';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardStatusToken::class, ReturnRing::class];
    }
}
