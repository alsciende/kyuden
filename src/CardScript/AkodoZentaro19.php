<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Akodo Zentarō.
 */
final readonly class AkodoZentaro19 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '19-akodo-zentaro';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
