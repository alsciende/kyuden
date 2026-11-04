<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Sharpen the Mind.
 */
final readonly class SharpenTheMind21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-sharpen-the-mind';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
