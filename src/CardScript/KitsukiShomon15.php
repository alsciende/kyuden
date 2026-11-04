<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kitsuki Shomon.
 */
final readonly class KitsukiShomon15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-kitsuki-shomon';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
