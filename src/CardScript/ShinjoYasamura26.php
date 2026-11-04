<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Shinjo Yasamura.
 */
final readonly class ShinjoYasamura26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-shinjo-yasamura';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
