<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Moto Nergüi.
 */
final readonly class MotoNergui08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-moto-nergui';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
