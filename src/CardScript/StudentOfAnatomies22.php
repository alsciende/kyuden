<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Student of Anatomies.
 */
final readonly class StudentOfAnatomies22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-student-of-anatomies';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
