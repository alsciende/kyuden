<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Staging Ground.
 */
final readonly class StagingGround01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-staging-ground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
