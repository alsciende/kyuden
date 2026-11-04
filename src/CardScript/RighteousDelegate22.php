<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Righteous Delegate.
 */
final readonly class RighteousDelegate22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-righteous-delegate';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
