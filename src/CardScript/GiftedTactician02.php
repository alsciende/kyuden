<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Gifted Tactician.
 */
final readonly class GiftedTactician02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-gifted-tactician';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
