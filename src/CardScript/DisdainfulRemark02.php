<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Disdainful Remark.
 */
final readonly class DisdainfulRemark02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-disdainful-remark';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
