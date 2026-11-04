<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Tears of Amaterasu.
 */
final readonly class TearsOfAmaterasu02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-tears-of-amaterasu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
