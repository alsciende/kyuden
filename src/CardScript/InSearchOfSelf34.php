<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * In Search of Self.
 */
final readonly class InSearchOfSelf34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-in-search-of-self';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
