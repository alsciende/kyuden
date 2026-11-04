<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Meditations on the Tao.
 */
final readonly class MeditationsOnTheTao01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-meditations-on-the-tao';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
