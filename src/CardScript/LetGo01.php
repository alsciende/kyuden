<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Let Go.
 */
final readonly class LetGo01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-let-go';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
