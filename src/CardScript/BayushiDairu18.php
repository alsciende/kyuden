<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveStatusToken;

/**
 * Bayushi Dairu.
 */
final readonly class BayushiDairu18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-bayushi-dairu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveStatusToken::class];
    }
}
