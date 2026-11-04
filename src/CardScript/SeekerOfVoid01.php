<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Seeker of Void.
 */
final readonly class SeekerOfVoid01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-seeker-of-void';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
