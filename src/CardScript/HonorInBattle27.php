<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Honor in Battle.
 */
final readonly class HonorInBattle27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-honor-in-battle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
