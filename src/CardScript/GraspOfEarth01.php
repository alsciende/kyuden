<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Grasp of Earth.
 */
final readonly class GraspOfEarth01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-grasp-of-earth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
