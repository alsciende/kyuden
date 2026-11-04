<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kiku Matsuri.
 */
final readonly class KikuMatsuri16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-kiku-matsuri';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
