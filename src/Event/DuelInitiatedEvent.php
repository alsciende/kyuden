<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\DuelType;
use App\GameLog\GameLog;

/**
 * A player has initiated a duel (InitiateDuel).
 */
#[GameLog('{challenger} initiates a {type} duel: {challengerCards} against {challengedCards}')]
final readonly class DuelInitiatedEvent implements GameEventInterface
{
    /**
     * @param list<PhysicalCard> $challengerCards
     * @param list<PhysicalCard> $challengedCards
     */
    public function __construct(
        public Game $game,
        public Player $challenger,
        public Player $challenged,
        public DuelType $type,
        public array $challengerCards,
        public array $challengedCards,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
