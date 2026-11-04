<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Castle of the Forgotten.
 */
final readonly class CastleOfTheForgotten40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-castle-of-the-forgotten';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
