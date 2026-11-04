<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\SwitchConflictType;

/**
 * Khan's Ordu.
 */
final readonly class KhanSOrdu17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-khan-s-ordu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchConflictType::class];
    }
}
