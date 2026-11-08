<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Root Out Heresy.
 */
final readonly class RootOutHeresy24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-root-out-heresy';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
