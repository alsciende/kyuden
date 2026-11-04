<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Winter Court Hosts.
 */
final readonly class WinterCourtHosts18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-winter-court-hosts';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
