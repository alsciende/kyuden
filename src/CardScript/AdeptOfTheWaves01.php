<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Adept of the Waves.
 */
final readonly class AdeptOfTheWaves01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-adept-of-the-waves';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
