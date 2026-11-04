<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Outflank.
 */
final readonly class Outflank23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-outflank';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
