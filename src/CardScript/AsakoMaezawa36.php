<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asako Maezawa.
 */
final readonly class AsakoMaezawa36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-asako-maezawa';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
