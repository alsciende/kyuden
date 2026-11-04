<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Moto Ariq.
 */
final readonly class MotoAriq27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-moto-ariq';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
