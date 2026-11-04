<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Akodo Kaede.
 */
final readonly class AkodoKaede20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-akodo-kaede';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
