<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Enum\Phase;

/**
 * A phase has begun (the transitions `*_phase_began`): the triggering condition of the cards reacting to a phase
 * beginning ("After the conflict phase begins", "After a phase begins"). Not logged: the transition writes its entry.
 */
final readonly class PhaseBeganEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Phase $phase,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'phase_began';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['phase' => $this->phase->value];
    }
}
