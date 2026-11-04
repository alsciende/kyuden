<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Blatant Swindler.
 */
final readonly class BlatantSwindler31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-blatant-swindler';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
