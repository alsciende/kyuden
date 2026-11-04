<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Smuggler's Cove.
 */
final readonly class SmugglerSCove23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-smuggler-s-cove';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
