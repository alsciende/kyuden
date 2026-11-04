<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Command By Name.
 */
final readonly class CommandByName33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-command-by-name';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
