<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Heartless Intimidator.
 */
final readonly class HeartlessIntimidator09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-heartless-intimidator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
