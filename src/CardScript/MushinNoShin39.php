<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mushin no Shin.
 */
final readonly class MushinNoShin39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-mushin-no-shin';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
