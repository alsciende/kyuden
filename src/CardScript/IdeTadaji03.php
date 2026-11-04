<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ide Tadaji.
 */
final readonly class IdeTadaji03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-ide-tadaji';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
