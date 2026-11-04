<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Bonsai Garden.
 */
final readonly class BonsaiGarden10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-bonsai-garden';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
