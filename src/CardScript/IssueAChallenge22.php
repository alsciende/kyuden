<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Issue a Challenge.
 */
final readonly class IssueAChallenge22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-issue-a-challenge';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
