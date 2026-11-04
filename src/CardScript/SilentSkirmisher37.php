<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Silent Skirmisher.
 */
final readonly class SilentSkirmisher37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-silent-skirmisher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
