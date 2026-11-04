<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Cunning Negotiator.
 */
final readonly class CunningNegotiator30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-cunning-negotiator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
