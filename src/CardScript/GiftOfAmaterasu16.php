<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Gift of Amaterasu.
 */
final readonly class GiftOfAmaterasu16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-gift-of-amaterasu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
