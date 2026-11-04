<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Way With Words.
 */
final readonly class WayWithWords31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-way-with-words';
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
