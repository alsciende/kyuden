<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Blackmail.
 */
final readonly class Blackmail01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-blackmail';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
