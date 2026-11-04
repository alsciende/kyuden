<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Daimyō's Favor.
 */
final readonly class DaimyoSFavor01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-daimyo-s-favor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
