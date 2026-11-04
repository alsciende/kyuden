<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Embrace Death.
 */
final readonly class EmbraceDeath27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-embrace-death';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
