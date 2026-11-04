<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ikebana Artisan.
 */
final readonly class IkebanaArtisan12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-ikebana-artisan';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
