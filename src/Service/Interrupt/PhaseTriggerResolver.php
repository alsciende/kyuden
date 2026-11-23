<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Enum\Phase;
use App\Message\ActionInterface;
use App\Trigger\PhaseBeginningTrigger;
use App\Trigger\PhaseEndingTrigger;

/**
 * A phase would begin or end (PhaseBeginningTrigger, PhaseEndingTrigger): the steps `<phase>_phase_began` and
 * `<phase>_phase_ended`, those of them marked `interrupt` (a card interrupts them).
 */
final readonly class PhaseTriggerResolver implements ImminentTriggerResolverInterface
{
    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        return [];
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        if (preg_match('/^(\w+)_phase_(began|ended)$/', $transition, $matches) !== 1) {
            return [];
        }

        $phase = Phase::tryFrom($matches[1]);
        if (! $phase instanceof Phase) {
            return [];
        }

        return [$matches[2] === 'began' ? new PhaseBeginningTrigger($phase) : new PhaseEndingTrigger($phase)];
    }
}
