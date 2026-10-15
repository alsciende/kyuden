<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\GameLog\GameLog;

/**
 * A player has declared a conflict (DeclareConflict).
 */
#[GameLog('{player} declares a {type} conflict against {defender} for {ring}, attacking {province} with {attackers}')]
final readonly class ConflictDeclaredEvent implements GameEventInterface
{
    /**
     * @param list<PhysicalCard> $attackers
     */
    public function __construct(
        public Game $game,
        public Player $player,
        public Player $defender,
        public Element $ring,
        public ConflictType $type,
        public PhysicalCard $province,
        public array $attackers,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
