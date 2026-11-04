<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Akodo Toshiro.
 */
final readonly class AkodoToshiro05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-akodo-toshiro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
