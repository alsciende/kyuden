<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Withstand the Darkness.
 */
final readonly class WithstandTheDarkness23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-withstand-the-darkness';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
