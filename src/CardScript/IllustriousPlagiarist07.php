<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Illustrious Plagiarist.
 */
final readonly class IllustriousPlagiarist07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-illustrious-plagiarist';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
