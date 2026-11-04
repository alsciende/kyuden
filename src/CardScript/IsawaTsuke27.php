<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Isawa Tsuke.
 */
final readonly class IsawaTsuke27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-isawa-tsuke';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
