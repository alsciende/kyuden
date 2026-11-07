<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RemoveFromGame;

/**
 * Dragon Tattoo.
 */
final readonly class DragonTattoo25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-dragon-tattoo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RemoveFromGame::class];
    }
}
