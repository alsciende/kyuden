<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shosuro Takao.
 */
final readonly class ShosuroTakao15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-shosuro-takao';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
