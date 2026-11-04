<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Paralyzing Delicacy.
 */
final readonly class ParalyzingDelicacy29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-paralyzing-delicacy';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
