<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sabotage.
 */
final readonly class Sabotage10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-sabotage';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
