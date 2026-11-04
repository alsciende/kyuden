<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Young Harrier.
 */
final readonly class YoungHarrier06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-young-harrier';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
