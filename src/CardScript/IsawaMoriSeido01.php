<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Isawa Mori Seidō.
 */
final readonly class IsawaMoriSeido01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-isawa-mori-seido';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
