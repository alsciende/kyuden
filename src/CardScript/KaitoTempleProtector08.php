<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kaito Temple Protector.
 */
final readonly class KaitoTempleProtector08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-kaito-temple-protector';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
