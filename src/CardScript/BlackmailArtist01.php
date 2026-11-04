<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Blackmail Artist.
 */
final readonly class BlackmailArtist01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-blackmail-artist';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
