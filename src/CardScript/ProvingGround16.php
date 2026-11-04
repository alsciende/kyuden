<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Proving Ground.
 */
final readonly class ProvingGround16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-proving-ground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
