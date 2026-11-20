<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;

/**
 * The honor dials of a bid are revealed, every one of them (after the HonorBidRevealedEvent of each player): the bid of
 * the draw phase, or the bid of a duel, by its players only. The triggering condition of the cards reacting to the
 * honor dials revealed, which read the bids in the state. Not logged: each dial revealed writes its entry.
 */
final readonly class HonorDialsRevealedEvent implements TriggeringEventInterface
{
    /**
     * @param list<Player> $players the players who revealed their dial
     */
    public function __construct(
        public Game $game,
        public array $players,
        /**
         * The bid of a duel, not of the draw phase.
         */
        public bool $duel,
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
        return 'honor_dials_revealed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['bid' => $this->duel ? 'duel' : 'draw'];
    }

    /**
     * The player has revealed their dial in this bid.
     */
    public function revealedBy(Player $player): bool
    {
        return in_array($player, $this->players, true);
    }
}
