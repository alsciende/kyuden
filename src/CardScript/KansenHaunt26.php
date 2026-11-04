<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kansen Haunt.
 */
final readonly class KansenHaunt26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-kansen-haunt';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
