<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Master of Many Lifetimes.
 */
final readonly class MasterOfManyLifetimes33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-master-of-many-lifetimes';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
