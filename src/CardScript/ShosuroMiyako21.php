<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveStatusToken;

/**
 * Shosuro Miyako.
 */
final readonly class ShosuroMiyako21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-shosuro-miyako';
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
