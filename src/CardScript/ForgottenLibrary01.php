<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Forgotten Library.
 */
final readonly class ForgottenLibrary01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-forgotten-library';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
