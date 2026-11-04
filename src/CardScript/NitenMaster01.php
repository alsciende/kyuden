<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Niten Master.
 */
final readonly class NitenMaster01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-niten-master';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
