<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sanguine Mastery.
 */
final readonly class SanguineMastery38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-sanguine-mastery';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
