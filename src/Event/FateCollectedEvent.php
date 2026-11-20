<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has collected fate during the dynasty phase (CollectFate): the triggering condition of the cards reacting
 * to it (Shiro Shinjo).
 */
#[GameLog('{player} collects {amount} fate')]
final readonly class FateCollectedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
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
        return 'fate_collected';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
