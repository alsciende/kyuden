<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * The Path of Man.
 */
final readonly class ThePathOfMan08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-the-path-of-man';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
