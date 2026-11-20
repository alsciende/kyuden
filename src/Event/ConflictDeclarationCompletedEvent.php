<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\Element;

/**
 * The declaration of a conflict is complete, its attacked province revealed (3.2): the triggering condition of the cards
 * reacting to an attack declared (the reactions in `react_to_conflict_declaration`), which read the conflict in the
 * state. Not logged: the transition `attacked_province_revealed` writes its entry.
 */
final readonly class ConflictDeclarationCompletedEvent implements TriggeringEventInterface
{
    /**
     * @param list<PhysicalCard> $attackers
     */
    public function __construct(
        public Game $game,
        public Player $attacker,
        public Player $defender,
        public Element $ring,
        public ConflictType $type,
        /**
         * The province the conflict is declared against, where the contested ring is.
         */
        public ProvinceCard $province,
        public array $attackers,
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
        return 'conflict_declaration_completed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'attacker' => $this->attacker->getId(),
            'defender' => $this->defender->getId(),
            'province' => $this->province->getId(),
        ];
    }
}
