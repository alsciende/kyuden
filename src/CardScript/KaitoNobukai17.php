<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kaito Nobukai.
 */
final readonly class KaitoNobukai17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-kaito-nobukai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
