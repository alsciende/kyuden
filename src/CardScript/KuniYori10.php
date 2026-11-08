<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kuni Yori.
 */
final readonly class KuniYori10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-kuni-yori';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
