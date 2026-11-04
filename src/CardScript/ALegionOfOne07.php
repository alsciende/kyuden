<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * A Legion of One.
 */
final readonly class ALegionOfOne07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-a-legion-of-one';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
