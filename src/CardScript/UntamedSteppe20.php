<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\TurnProvinceFacedown;

/**
 * Untamed Steppe.
 */
final readonly class UntamedSteppe20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-untamed-steppe';
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
