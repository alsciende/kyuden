<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Yogo Asami.
 */
final readonly class YogoAsami26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-yogo-asami';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
