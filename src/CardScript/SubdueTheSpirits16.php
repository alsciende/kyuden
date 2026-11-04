<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Subdue the Spirits.
 */
final readonly class SubdueTheSpirits16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-subdue-the-spirits';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
