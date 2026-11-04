<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ethereal Dreamer.
 */
final readonly class EtherealDreamer13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-ethereal-dreamer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
