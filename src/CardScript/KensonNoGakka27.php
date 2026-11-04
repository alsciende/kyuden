<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kenson no Gakka.
 */
final readonly class KensonNoGakka27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-kenson-no-gakka';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
