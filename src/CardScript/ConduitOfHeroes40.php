<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Conduit of Heroes.
 */
final readonly class ConduitOfHeroes40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-conduit-of-heroes';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
