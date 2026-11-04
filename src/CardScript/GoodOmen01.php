<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Good Omen.
 */
final readonly class GoodOmen01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-good-omen';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
