<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Void Fist.
 */
final readonly class VoidFist13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-void-fist';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
