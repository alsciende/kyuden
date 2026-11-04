<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Akodo Makoto.
 */
final readonly class AkodoMakoto18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-akodo-makoto';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
