<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Recalled Defenses.
 */
final readonly class RecalledDefenses29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-recalled-defenses';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
