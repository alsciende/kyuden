<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mantis Seafarer.
 */
final readonly class MantisSeafarer11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-mantis-seafarer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
