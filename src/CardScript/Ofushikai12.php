<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ofushikai.
 */
final readonly class Ofushikai12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-ofushikai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }
}
