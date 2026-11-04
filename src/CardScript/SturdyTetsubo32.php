<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sturdy Tetsubō.
 */
final readonly class SturdyTetsubo32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-sturdy-tetsubo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }
}
