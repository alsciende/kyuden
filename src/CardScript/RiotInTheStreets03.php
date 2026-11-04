<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Riot in the Streets.
 */
final readonly class RiotInTheStreets03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-riot-in-the-streets';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
