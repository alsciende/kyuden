<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kabuki Hero.
 */
final readonly class KabukiHero03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-kabuki-hero';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
