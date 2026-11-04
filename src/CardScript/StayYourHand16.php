<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Stay Your Hand.
 */
final readonly class StayYourHand16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-stay-your-hand';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
