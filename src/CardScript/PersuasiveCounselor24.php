<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Persuasive Counselor.
 */
final readonly class PersuasiveCounselor24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-persuasive-counselor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
