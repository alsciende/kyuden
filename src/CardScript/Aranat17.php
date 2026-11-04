<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Aranat.
 */
final readonly class Aranat17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-aranat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
