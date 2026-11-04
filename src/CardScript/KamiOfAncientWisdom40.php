<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kami of Ancient Wisdom.
 */
final readonly class KamiOfAncientWisdom40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-kami-of-ancient-wisdom';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
