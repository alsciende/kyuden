<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Inferno Guard Invoker.
 */
final readonly class InfernoGuardInvoker24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-inferno-guard-invoker';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
