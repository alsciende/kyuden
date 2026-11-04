<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Miya Mystic.
 */
final readonly class MiyaMystic01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-miya-mystic';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
