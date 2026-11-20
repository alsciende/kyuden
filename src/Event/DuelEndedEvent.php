<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * The duel has ended (EndDuel), the duel no longer in the state: the triggering condition of the cards reacting to a
 * duel resolved (Kyūden Kakita).
 */
#[GameLog('The duel ends')]
final readonly class DuelEndedEvent implements TriggeringEventInterface
{
    /**
     * @param list<string> $characterIds the characters involved in the duel, of both sides
     */
    public function __construct(
        public Game $game,
        public array $characterIds,
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
        return 'duel_ended';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [];
    }

    /**
     * The character was involved in the duel.
     */
    public function wasInvolved(PhysicalCard $character): bool
    {
        return in_array($character->getId(), $this->characterIds, true);
    }
}
