<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Steward of Cryptic Lore.
 */
final readonly class StewardOfCrypticLore37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-steward-of-cryptic-lore';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
