<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCards;

/**
 * Bayushi's Whisperers.
 */
final readonly class BayushiSWhisperers15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-bayushi-s-whisperers';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
