<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Prodigy of the Waves.
 */
final readonly class ProdigyOfTheWaves04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-prodigy-of-the-waves';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
