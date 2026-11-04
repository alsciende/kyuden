<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Gallant Quartermaster.
 */
final readonly class GallantQuartermaster24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-gallant-quartermaster';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
