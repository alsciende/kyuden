<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;

/**
 * Fate has been removed from the characters in the fate phase (4.2), once from each of them (after the
 * FateRemovedFromCardEvent of each): the triggering condition of the cards reacting to it as a whole (Starry Heaven
 * Sanctuary). Not logged: the transition `fate_removed` writes its entry.
 */
final readonly class FatePhaseFateRemovedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        /**
         * The fate removed, from every character.
         */
        public int $amount,
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
        return 'fate_phase_fate_removed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [];
    }
}
