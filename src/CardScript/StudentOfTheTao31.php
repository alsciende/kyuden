<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Student of the Tao.
 */
final readonly class StudentOfTheTao31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-student-of-the-tao';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
