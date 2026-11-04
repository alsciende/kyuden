<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Stoic Rival.
 */
final readonly class StoicRival32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-stoic-rival';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
