<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asahina Takamori.
 */
final readonly class AsahinaTakamori18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-asahina-takamori';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
