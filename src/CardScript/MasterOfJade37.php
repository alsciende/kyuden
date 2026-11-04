<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Master of Jade.
 */
final readonly class MasterOfJade37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-master-of-jade';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
