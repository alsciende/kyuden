<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ardent Omoidasu.
 */
final readonly class ArdentOmoidasu27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-ardent-omoidasu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
