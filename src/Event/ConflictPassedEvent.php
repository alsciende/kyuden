<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has passed their conflict opportunity (PassConflict): the triggering condition of the cards reacting to a
 * pass on declaring a conflict.
 */
#[GameLog('{player} passes their conflict opportunity')]
final readonly class ConflictPassedEvent implements TriggeringEventInterface
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
        return 'conflict_passed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
