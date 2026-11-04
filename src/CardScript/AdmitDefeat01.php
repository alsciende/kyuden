<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Admit Defeat.
 */
final readonly class AdmitDefeat01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-admit-defeat';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
