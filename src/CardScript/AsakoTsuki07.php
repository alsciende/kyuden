<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asako Tsuki.
 */
final readonly class AsakoTsuki07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-asako-tsuki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
