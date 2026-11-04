<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Outwit.
 */
final readonly class Outwit01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-outwit';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
