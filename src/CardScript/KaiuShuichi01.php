<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kaiu Shuichi.
 */
final readonly class KaiuShuichi01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-kaiu-shuichi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
