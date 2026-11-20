<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * The resolution of a conflict is over, its participants returned home (3.2.7, transition `returned_home`): the
 * triggering condition of the cards reacting to the end of the resolution of a conflict ("After the resolution of a
 * conflict in which this character was attacking, if a province was broken in the conflict"). Not logged: the
 * transition writes its entry.
 */
final readonly class ConflictResolvedEvent implements TriggeringEventInterface
{
    /**
     * @param list<string> $attackerIds        the characters that were attacking when it was resolved
     * @param list<string> $brokenProvinceIds the provinces it broke (3.2.5), not those broken by an effect
     */
    public function __construct(
        public Game $game,
        public Player $attacker,
        public array $attackerIds,
        public array $brokenProvinceIds,
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
        return 'conflict_resolved';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['attacker' => $this->attacker->getId()];
    }

    /**
     * The character was attacking in the conflict.
     */
    public function wasAttacking(PhysicalCard $character): bool
    {
        return in_array($character->getId(), $this->attackerIds, true);
    }
}
