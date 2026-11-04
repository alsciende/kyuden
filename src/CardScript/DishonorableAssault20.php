<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Dishonorable Assault.
 */
final readonly class DishonorableAssault20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-dishonorable-assault';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
