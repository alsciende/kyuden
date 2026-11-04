<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Natural Negotiator.
 */
final readonly class NaturalNegotiator22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-natural-negotiator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
