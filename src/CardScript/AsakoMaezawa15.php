<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asako Maezawa.
 */
final readonly class AsakoMaezawa15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-asako-maezawa';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
