<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kamayari.
 */
final readonly class Kamayari03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-kamayari';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
