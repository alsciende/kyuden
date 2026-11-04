<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * I Am Ready.
 */
final readonly class IAmReady01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-i-am-ready';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
