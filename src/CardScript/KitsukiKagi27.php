<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RemoveFromGame;

/**
 * Kitsuki Kagi.
 */
final readonly class KitsukiKagi27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-kitsuki-kagi';
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
