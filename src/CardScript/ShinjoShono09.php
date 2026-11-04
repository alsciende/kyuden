<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shinjo Shono.
 */
final readonly class ShinjoShono09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-shinjo-shono';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
