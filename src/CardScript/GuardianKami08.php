<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Guardian Kami.
 */
final readonly class GuardianKami08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-guardian-kami';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
