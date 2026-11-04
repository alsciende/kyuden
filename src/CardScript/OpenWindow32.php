<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Open Window.
 */
final readonly class OpenWindow32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-open-window';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
