<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Quarrelsome Youth.
 */
final readonly class QuarrelsomeYouth23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-quarrelsome-youth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
