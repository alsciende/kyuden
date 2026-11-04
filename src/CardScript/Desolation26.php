<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Desolation.
 */
final readonly class Desolation26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-desolation';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
