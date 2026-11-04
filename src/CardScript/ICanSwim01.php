<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * I Can Swim.
 */
final readonly class ICanSwim01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-i-can-swim';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
