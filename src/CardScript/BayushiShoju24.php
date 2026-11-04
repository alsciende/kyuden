<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bayushi Shoju.
 */
final readonly class BayushiShoju24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-bayushi-shoju';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
