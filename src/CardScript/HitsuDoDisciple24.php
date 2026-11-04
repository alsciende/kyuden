<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Hitsu-do Disciple.
 */
final readonly class HitsuDoDisciple24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-hitsu-do-disciple';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
