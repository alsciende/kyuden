<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shinjo Tatsuo.
 */
final readonly class ShinjoTatsuo01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shinjo-tatsuo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
