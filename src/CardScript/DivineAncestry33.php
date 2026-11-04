<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Divine Ancestry.
 */
final readonly class DivineAncestry33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-divine-ancestry';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
