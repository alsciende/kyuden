<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;

/**
 * A player has passed in the dynasty phase (`play_cards_from_provinces`), with the fate they gained for passing first:
 * the triggering condition of the cards reacting to it ("After you pass in the dynasty phase", "After you gain fate for
 * passing in the dynasty phase"). Not logged: OpportunityPassedEvent and FateGainedEvent write their entries.
 */
final readonly class DynastyPhasePassedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        /**
         * The fate gained for passing first, 0 otherwise.
         */
        public int $fateGained,
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
        return 'dynasty_phase_passed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
