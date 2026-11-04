<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sinister Peacekeeper.
 */
final readonly class SinisterPeacekeeper36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-sinister-peacekeeper';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
