<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\TakeImperialFavor;

/**
 * Fawning Diplomat.
 */
final readonly class FawningDiplomat05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-fawning-diplomat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [TakeImperialFavor::class];
    }
}
