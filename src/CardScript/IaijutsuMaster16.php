<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Iaijutsu Master.
 */
final readonly class IaijutsuMaster16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-iaijutsu-master';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
