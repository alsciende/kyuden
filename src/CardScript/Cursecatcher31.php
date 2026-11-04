<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Cursecatcher.
 */
final readonly class Cursecatcher31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-cursecatcher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
