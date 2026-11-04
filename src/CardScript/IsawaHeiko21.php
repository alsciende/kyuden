<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Isawa Heiko.
 */
final readonly class IsawaHeiko21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-isawa-heiko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
