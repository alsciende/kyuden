<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * The defending player has declared their defenders (DeclareDefenders), once they are in the state: the triggering
 * condition of the cards reacting to a character declared as a defender.
 */
#[GameLog('{player} declares {defenders} as defenders')]
final readonly class DefendersDeclaredEvent implements TriggeringEventInterface
{
    /**
     * @param list<PhysicalCard> $defenders
     */
    public function __construct(
        public Game $game,
        public Player $player,
        public array $defenders,
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
        return 'defenders_declared';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
