<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hiruma Yoshino.
 */
final readonly class HirumaYoshino20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-hiruma-yoshino';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
