<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Soshi Diviner.
 */
final readonly class SoshiDiviner33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-soshi-diviner';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
