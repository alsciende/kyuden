<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Honed Nodachi.
 */
final readonly class HonedNodachi16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-honed-nodachi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
