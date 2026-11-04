<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Miyako's Undertaking.
 */
final readonly class MiyakoSUndertaking16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-miyako-s-undertaking';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
