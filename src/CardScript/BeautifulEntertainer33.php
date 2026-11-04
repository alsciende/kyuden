<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Beautiful Entertainer.
 */
final readonly class BeautifulEntertainer33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-beautiful-entertainer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
