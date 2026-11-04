<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ide Negotiator.
 */
final readonly class IdeNegotiator16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-ide-negotiator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
