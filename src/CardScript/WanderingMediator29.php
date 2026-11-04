<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Wandering Mediator.
 */
final readonly class WanderingMediator29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-wandering-mediator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
