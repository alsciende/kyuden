<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ikoma Kiyono.
 */
final readonly class IkomaKiyono22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-ikoma-kiyono';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
