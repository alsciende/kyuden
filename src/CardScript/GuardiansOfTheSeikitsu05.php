<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Guardians of the Seikitsu.
 */
final readonly class GuardiansOfTheSeikitsu05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-guardians-of-the-seikitsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
