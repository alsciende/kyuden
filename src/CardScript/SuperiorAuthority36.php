<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Superior Authority.
 */
final readonly class SuperiorAuthority36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-superior-authority';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
