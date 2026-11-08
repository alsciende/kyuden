<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Akodo Motivator.
 */
final readonly class AkodoMotivator27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-akodo-motivator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
