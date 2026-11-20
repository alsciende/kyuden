<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * A character in play has moved to the conflict (MoveToConflict), on the side of its controller: the triggering
 * condition of the cards reacting to a character moving to a conflict, which read the conflict in the state.
 */
#[GameLog('{card} moves to the conflict')]
final readonly class CharacterMovedToConflictEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
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
        return 'character_moved_to_conflict';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId()];
    }
}
