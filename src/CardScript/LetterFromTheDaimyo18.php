<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Letter from the Daimyō.
 */
final readonly class LetterFromTheDaimyo18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-letter-from-the-daimyo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
