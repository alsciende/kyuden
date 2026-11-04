<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * In Defense of Rokugan.
 */
final readonly class InDefenseOfRokugan16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-in-defense-of-rokugan';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
