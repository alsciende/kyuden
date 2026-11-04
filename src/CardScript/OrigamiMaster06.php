<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Origami Master.
 */
final readonly class OrigamiMaster06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-origami-master';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
