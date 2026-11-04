<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Iuchi Shahai.
 */
final readonly class IuchiShahai38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-iuchi-shahai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
