<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kunshu.
 */
final readonly class Kunshu36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-kunshu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }
}
