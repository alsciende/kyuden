<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * Fate has moved from a holder to another (MoveFate): the fate pool of a player, a card or a ring. The triggering
 * condition of the cards reacting to fate placed on or removed from a character, by a card effect.
 */
#[GameLog('{amount} fate moves from {source} to {target}')]
final readonly class FateMovedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player|PhysicalCard|Element $source,
        public Player|PhysicalCard|Element $target,
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
        return 'fate_moved';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [];
    }
}
