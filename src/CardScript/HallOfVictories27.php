<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hall of Victories.
 */
final readonly class HallOfVictories27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-hall-of-victories';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
