<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Armament Artisan.
 */
final readonly class ArmamentArtisan18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-armament-artisan';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
