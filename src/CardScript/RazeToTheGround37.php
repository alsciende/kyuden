<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Raze to the Ground.
 */
final readonly class RazeToTheGround37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-raze-to-the-ground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
