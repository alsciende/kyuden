<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Deceptive Offer.
 */
final readonly class DeceptiveOffer12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-deceptive-offer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
