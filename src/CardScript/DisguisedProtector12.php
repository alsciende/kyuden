<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Disguised Protector.
 */
final readonly class DisguisedProtector12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-disguised-protector';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
