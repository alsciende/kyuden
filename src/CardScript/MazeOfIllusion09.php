<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Maze of Illusion.
 */
final readonly class MazeOfIllusion09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-maze-of-illusion';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
