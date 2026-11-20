<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has won the conflict, with the declared skills: the triggering condition of the cards reacting to a conflict
 * won or lost, which read the conflict still in the state (3.2.3, the reactions in `react_to_conflict_result`).
 */
#[GameLog('{player} wins the conflict ({attackerSkill} against {defenderSkill})')]
final readonly class ConflictWonEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $attackerSkill,
        public int $defenderSkill,
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
        return 'conflict_won';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['winner' => $this->player->getId()];
    }
}
