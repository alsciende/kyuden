<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Master of the Court.
 */
final readonly class MasterOfTheCourt33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-master-of-the-court';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
