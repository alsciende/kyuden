<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Eternal Watch.
 */
final readonly class TheEternalWatch38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-the-eternal-watch';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
