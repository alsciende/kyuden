<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has won the duel, its result determined (the duel still in the state): the triggering condition of the
 * cards reacting to a duel won ("After attached character wins a duel"), which read the characters involved in the
 * state.
 */
#[GameLog('{player} wins the duel')]
final readonly class DuelWonEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
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
        return 'duel_won';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
