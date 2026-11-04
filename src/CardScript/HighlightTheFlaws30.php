<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Highlight the Flaws.
 */
final readonly class HighlightTheFlaws30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-highlight-the-flaws';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
