<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fortified Assembly.
 */
final readonly class FortifiedAssembly22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-fortified-assembly';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
