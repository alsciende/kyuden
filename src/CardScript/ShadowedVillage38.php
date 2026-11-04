<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shadowed Village.
 */
final readonly class ShadowedVillage38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-shadowed-village';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
