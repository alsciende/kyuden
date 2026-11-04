<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Temple of the Dragons.
 */
final readonly class TempleOfTheDragons16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-temple-of-the-dragons';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
