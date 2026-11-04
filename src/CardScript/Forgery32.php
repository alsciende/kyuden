<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Forgery.
 */
final readonly class Forgery32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-forgery';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
