<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kitsuki Kagi.
 */
final readonly class KitsukiKagi27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-kitsuki-kagi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
