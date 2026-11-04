<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fruitful Respite.
 */
final readonly class FruitfulRespite26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-fruitful-respite';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
