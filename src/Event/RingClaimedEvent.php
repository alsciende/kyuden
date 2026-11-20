<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * A player has claimed a ring: the winner of the conflict the contested ring, or a player by an effect (ClaimRing). The
 * triggering condition of the cards reacting to a ring claimed (after a conflict, in `react_to_ring_claim`).
 */
#[GameLog('{player} claims {ring}')]
final readonly class RingClaimedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public Element $ring,
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
        return 'ring_claimed';
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
