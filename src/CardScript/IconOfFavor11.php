<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Icon of Favor.
 */
final readonly class IconOfFavor11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-icon-of-favor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
