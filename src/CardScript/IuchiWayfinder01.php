<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCard;

/**
 * Iuchi Wayfinder.
 */
final readonly class IuchiWayfinder01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-iuchi-wayfinder';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCard::class];
    }
}
