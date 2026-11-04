<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Northern Wall Sensei.
 */
final readonly class NorthernWallSensei03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-northern-wall-sensei';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
