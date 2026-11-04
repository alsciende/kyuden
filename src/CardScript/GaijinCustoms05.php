<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Gaijin Customs.
 */
final readonly class GaijinCustoms05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-gaijin-customs';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
