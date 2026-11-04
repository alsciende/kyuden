<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ethereal Alignment.
 */
final readonly class EtherealAlignment30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-ethereal-alignment';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
