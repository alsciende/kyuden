<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Relentless Inquisitor.
 */
final readonly class RelentlessInquisitor41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-relentless-inquisitor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
