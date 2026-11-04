<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Pacifist Philosopher.
 */
final readonly class PacifistPhilosopher08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-pacifist-philosopher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
