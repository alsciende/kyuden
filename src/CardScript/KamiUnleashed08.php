<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kami Unleashed.
 */
final readonly class KamiUnleashed08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-kami-unleashed';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
