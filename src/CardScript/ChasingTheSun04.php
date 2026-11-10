<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveConflict;

/**
 * Chasing the Sun.
 */
final readonly class ChasingTheSun04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-chasing-the-sun';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveConflict::class];
    }
}
