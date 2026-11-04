<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Fertile Fields.
 */
final readonly class FertileFields01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-fertile-fields';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
