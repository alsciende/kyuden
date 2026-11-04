<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ikoma Prodigy.
 */
final readonly class IkomaProdigy01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-ikoma-prodigy';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
