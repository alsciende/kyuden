<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kirei-ko.
 */
final readonly class KireiKo16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-kirei-ko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
