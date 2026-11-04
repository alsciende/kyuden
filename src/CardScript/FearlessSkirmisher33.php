<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveStatusToken;

/**
 * Fearless Skirmisher.
 */
final readonly class FearlessSkirmisher33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-fearless-skirmisher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveStatusToken::class];
    }
}
