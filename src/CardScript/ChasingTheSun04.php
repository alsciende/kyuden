<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Chasing the Sun.
 */
final readonly class ChasingTheSun04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-chasing-the-sun';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
