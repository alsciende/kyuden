<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Tengu Sensei.
 */
final readonly class TenguSensei14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-tengu-sensei';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
