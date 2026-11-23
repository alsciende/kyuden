<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Enum\Phase;

/**
 * A phase would end (its `<phase>_phase_ended` step): the interrupts "when the conflict phase ends" come before it
 * does. Nothing cancels it.
 */
final readonly class PhaseEndingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Phase $phase,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'phase_ending';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['phase' => $this->phase->value];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return 'The {phase} phase would end';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['phase' => $this->phase];
    }
}
