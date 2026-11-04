<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ujiaki's Offer.
 */
final readonly class UjiakiSOffer41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-ujiaki-s-offer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
