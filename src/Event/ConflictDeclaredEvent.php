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
 * A player has declared a conflict (DeclareConflict), before the attacked province is revealed: the triggering condition
 * of the cards reacting to the declaration itself (The Crashing Wave, before the province is revealed; Enlightened
 * Warrior, to the ring chosen). The other reactions to the declaration wait for the province revealed
 * (ConflictDeclarationCompletedEvent).
 */
#[GameLog('{player} declares a {type} conflict against {defender} for {ring}, attacking {province} with {attackers}')]
final readonly class ConflictDeclaredEvent implements TriggeringEventInterface
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
        /**
         * The fate the contested ring had, which the attacking player takes (Enlightened Warrior).
         */
        public int $ringFate,
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
        return 'conflict_declared';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'attacker' => $this->player->getId(),
            'defender' => $this->defender->getId(),
            'province' => $this->province->getId(),
        ];
    }
}
