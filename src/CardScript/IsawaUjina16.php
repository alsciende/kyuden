<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RemoveFromGame;

/**
 * Isawa Ujina.
 */
final readonly class IsawaUjina16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-isawa-ujina';
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
