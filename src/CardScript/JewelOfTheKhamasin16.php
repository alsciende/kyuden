<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Jewel of the Khamasin.
 */
final readonly class JewelOfTheKhamasin16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-jewel-of-the-khamasin';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
