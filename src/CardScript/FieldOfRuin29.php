<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Field of Ruin.
 */
final readonly class FieldOfRuin29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-field-of-ruin';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
