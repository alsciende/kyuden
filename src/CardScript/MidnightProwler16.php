<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCards;

/**
 * Midnight Prowler.
 */
final readonly class MidnightProwler16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-midnight-prowler';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
