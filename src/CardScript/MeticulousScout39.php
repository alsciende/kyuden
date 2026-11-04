<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Meticulous Scout.
 */
final readonly class MeticulousScout39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-meticulous-scout';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
