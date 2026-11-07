<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RemoveFromGame;

/**
 * Trading on the Sand Road.
 */
final readonly class TradingOnTheSandRoad17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-trading-on-the-sand-road';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RemoveFromGame::class];
    }
}
