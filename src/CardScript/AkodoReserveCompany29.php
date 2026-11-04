<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Akodo Reserve Company.
 */
final readonly class AkodoReserveCompany29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-akodo-reserve-company';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
