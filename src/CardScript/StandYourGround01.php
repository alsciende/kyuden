<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Stand Your Ground.
 */
final readonly class StandYourGround01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-stand-your-ground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
