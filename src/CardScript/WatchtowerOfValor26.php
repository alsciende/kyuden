<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Watchtower of Valor.
 */
final readonly class WatchtowerOfValor26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-watchtower-of-valor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
