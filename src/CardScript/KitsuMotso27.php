<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kitsu Motso.
 */
final readonly class KitsuMotso27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-kitsu-motso';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
