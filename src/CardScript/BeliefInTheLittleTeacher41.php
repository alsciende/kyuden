<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Belief in the Little Teacher.
 */
final readonly class BeliefInTheLittleTeacher41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-belief-in-the-little-teacher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }
}
