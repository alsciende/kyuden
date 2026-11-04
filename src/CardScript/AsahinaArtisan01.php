<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asahina Artisan.
 */
final readonly class AsahinaArtisan01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-asahina-artisan';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
