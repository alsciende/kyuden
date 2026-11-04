<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ivory Kingdoms Unicorn.
 */
final readonly class IvoryKingdomsUnicorn17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-ivory-kingdoms-unicorn';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
