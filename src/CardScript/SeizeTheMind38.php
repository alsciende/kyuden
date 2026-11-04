<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seize the Mind.
 */
final readonly class SeizeTheMind38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-seize-the-mind';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
