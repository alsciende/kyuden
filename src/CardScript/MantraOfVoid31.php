<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Mantra of Void.
 */
final readonly class MantraOfVoid31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-mantra-of-void';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
