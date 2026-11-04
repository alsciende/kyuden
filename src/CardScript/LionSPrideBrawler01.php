<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Lion's Pride Brawler.
 */
final readonly class LionSPrideBrawler01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-lion-s-pride-brawler';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
