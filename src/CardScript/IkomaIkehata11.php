<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ikoma Ikehata.
 */
final readonly class IkomaIkehata11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-ikoma-ikehata';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
