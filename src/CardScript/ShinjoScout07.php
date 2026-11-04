<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shinjo Scout.
 */
final readonly class ShinjoScout07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-shinjo-scout';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
