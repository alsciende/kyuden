<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Command Respect.
 */
final readonly class CommandRespect19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-command-respect';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
