<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Courteous Greeting.
 */
final readonly class CourteousGreeting31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-courteous-greeting';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
