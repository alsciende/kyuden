<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Secluded Shrine.
 */
final readonly class SecludedShrine08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-secluded-shrine';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
