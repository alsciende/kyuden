<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kuni Ritsuko.
 */
final readonly class KuniRitsuko07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-kuni-ritsuko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
