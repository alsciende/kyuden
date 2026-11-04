<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ShuffleIntoDeck;

/**
 * Karmic Balance.
 */
final readonly class KarmicBalance16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-karmic-balance';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ShuffleIntoDeck::class];
    }
}
