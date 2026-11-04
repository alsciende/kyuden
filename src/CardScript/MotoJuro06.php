<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Moto Juro.
 */
final readonly class MotoJuro06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-moto-juro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
