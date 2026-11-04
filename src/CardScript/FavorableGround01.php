<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Favorable Ground.
 */
final readonly class FavorableGround01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-favorable-ground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
