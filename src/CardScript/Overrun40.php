<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceStatusToken;

/**
 * Overrun.
 */
final readonly class Overrun40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-overrun';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceStatusToken::class];
    }
}
