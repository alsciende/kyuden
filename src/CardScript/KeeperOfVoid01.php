<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Keeper of Void.
 */
final readonly class KeeperOfVoid01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-keeper-of-void';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
