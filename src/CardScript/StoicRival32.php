<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\FillProvince;

/**
 * Stoic Rival.
 */
final readonly class StoicRival32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-stoic-rival';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
