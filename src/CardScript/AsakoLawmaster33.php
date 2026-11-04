<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asako Lawmaster.
 */
final readonly class AsakoLawmaster33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-asako-lawmaster';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
