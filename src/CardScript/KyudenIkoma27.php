<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kyūden Ikoma.
 */
final readonly class KyudenIkoma27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-kyuden-ikoma';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
