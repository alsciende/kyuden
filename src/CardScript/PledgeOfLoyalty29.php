<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Pledge of Loyalty.
 */
final readonly class PledgeOfLoyalty29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-pledge-of-loyalty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
