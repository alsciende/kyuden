<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\GrantConflictOpportunity;

/**
 * Hida O-Ushi.
 */
final readonly class HidaOUshi26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-hida-o-ushi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [GrantConflictOpportunity::class];
    }
}
