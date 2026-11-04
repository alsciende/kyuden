<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Defend the Wall.
 */
final readonly class DefendTheWall01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-defend-the-wall';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
