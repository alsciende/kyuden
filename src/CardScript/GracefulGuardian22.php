<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Graceful Guardian.
 */
final readonly class GracefulGuardian22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-graceful-guardian';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
