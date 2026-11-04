<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Give No Ground.
 */
final readonly class GiveNoGround19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-give-no-ground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
