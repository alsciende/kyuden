<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Into the Forbidden City.
 */
final readonly class IntoTheForbiddenCity21 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '21-into-the-forbidden-city';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
