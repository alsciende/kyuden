<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ReturnRing;

/**
 * Deduction.
 */
final readonly class Deduction23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-deduction';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnRing::class];
    }
}
