<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Invoke the Divine.
 */
final readonly class InvokeTheDivine22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-invoke-the-divine';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
