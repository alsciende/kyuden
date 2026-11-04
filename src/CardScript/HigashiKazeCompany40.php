<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Higashi Kaze Company.
 */
final readonly class HigashiKazeCompany40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-higashi-kaze-company';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
