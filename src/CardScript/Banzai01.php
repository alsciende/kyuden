<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Banzai!.
 */
final readonly class Banzai01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-banzai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
