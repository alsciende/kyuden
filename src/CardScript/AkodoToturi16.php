<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Akodo Toturi.
 */
final readonly class AkodoToturi16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-akodo-toturi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
