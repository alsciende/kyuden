<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asahina Maeko.
 */
final readonly class AsahinaMaeko25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-asahina-maeko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
