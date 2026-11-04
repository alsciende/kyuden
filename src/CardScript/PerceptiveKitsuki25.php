<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Perceptive Kitsuki.
 */
final readonly class PerceptiveKitsuki25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-perceptive-kitsuki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
