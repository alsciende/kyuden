<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\InitiateDuel;

/**
 * Arrogant Kakita.
 */
final readonly class ArrogantKakita39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-arrogant-kakita';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
