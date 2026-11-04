<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Discourage Pursuit.
 */
final readonly class DiscouragePursuit13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-discourage-pursuit';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
