<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * War Dog Master.
 */
final readonly class WarDogMaster04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-war-dog-master';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
