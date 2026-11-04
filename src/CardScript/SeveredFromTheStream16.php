<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Severed from the Stream.
 */
final readonly class SeveredFromTheStream16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-severed-from-the-stream';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
