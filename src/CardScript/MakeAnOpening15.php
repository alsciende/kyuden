<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Make an Opening.
 */
final readonly class MakeAnOpening15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-make-an-opening';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
