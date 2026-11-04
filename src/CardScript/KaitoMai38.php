<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kaito Mai.
 */
final readonly class KaitoMai38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-kaito-mai';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
