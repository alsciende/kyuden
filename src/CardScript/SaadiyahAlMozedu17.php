<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\TurnProvinceFacedown;

/**
 * Saadiyah al-Mozedu.
 */
final readonly class SaadiyahAlMozedu17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-saadiyah-al-mozedu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [TurnProvinceFacedown::class];
    }
}
