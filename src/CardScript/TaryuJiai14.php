<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Taryū-Jiai.
 */
final readonly class TaryuJiai14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-taryu-jiai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
