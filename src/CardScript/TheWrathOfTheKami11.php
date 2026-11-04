<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Wrath of the Kami.
 */
final readonly class TheWrathOfTheKami11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-the-wrath-of-the-kami';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
