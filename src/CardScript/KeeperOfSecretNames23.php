<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Keeper of Secret Names.
 */
final readonly class KeeperOfSecretNames23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-keeper-of-secret-names';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
