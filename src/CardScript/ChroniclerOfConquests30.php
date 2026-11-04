<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Chronicler of Conquests.
 */
final readonly class ChroniclerOfConquests30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-chronicler-of-conquests';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
