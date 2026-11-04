<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Master of the Swift Waves.
 */
final readonly class MasterOfTheSwiftWaves12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-master-of-the-swift-waves';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
