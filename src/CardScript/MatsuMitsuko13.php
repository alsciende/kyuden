<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Matsu Mitsuko.
 */
final readonly class MatsuMitsuko13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-matsu-mitsuko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
