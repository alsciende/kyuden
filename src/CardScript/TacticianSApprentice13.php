<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Tactician's Apprentice.
 */
final readonly class TacticianSApprentice13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-tactician-s-apprentice';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
