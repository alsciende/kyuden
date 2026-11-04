<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Against the Waves.
 */
final readonly class AgainstTheWaves01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-against-the-waves';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
