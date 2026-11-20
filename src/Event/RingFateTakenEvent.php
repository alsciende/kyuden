<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * The attacking player has taken the fate on the ring that became the contested ring (declaration, switch): the
 * triggering condition of the cards reacting to fate gained from a ring (moved from a ring by an effect:
 * FateMovedEvent).
 */
#[GameLog('{player} takes {amount} fate from {ring}')]
final readonly class RingFateTakenEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public Element $ring,
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
        return 'ring_fate_taken';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'player' => $this->player->getId(),
            'ring' => $this->ring->value,
        ];
    }
}
