<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Misinformation.
 */
final readonly class Misinformation04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-misinformation';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
