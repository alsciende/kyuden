<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\SwitchConflictType;

/**
 * Ikoma Ujiaki.
 */
final readonly class IkomaUjiaki41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-ikoma-ujiaki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchConflictType::class];
    }
}
