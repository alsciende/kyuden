<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Revered Ikoma.
 */
final readonly class ReveredIkoma36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-revered-ikoma';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
