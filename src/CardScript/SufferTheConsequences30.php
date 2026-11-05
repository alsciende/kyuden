<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\GrantConflictOpportunity;

/**
 * Suffer the Consequences.
 */
final readonly class SufferTheConsequences30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-suffer-the-consequences';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [GrantConflictOpportunity::class];
    }
}
