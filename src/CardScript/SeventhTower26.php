<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seventh Tower.
 */
final readonly class SeventhTower26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-seventh-tower';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
