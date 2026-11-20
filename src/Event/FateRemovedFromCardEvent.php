<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * Fate has been removed from a card, to the general token pool: by a card effect (RemoveFate), in the fate phase, or as
 * the card leaves play. The triggering condition of the cards reacting to fate removed from a character (moved from it:
 * FateMovedEvent; the fate phase as a whole: FatePhaseFateRemovedEvent).
 */
#[GameLog('{amount} fate is removed from {card}')]
final readonly class FateRemovedFromCardEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public int $amount,
        /**
         * Returned to the pool as the card leaves play, not removed from a card in play.
         */
        public bool $leavingPlay = false,
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
        return 'fate_removed_from_card';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId()];
    }
}
