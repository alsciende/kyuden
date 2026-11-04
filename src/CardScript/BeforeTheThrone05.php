<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Before the Throne.
 */
final readonly class BeforeTheThrone05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-before-the-throne';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
