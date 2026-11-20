<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * A card has left play (LeavePlayListener: discarded, returned to the hand…), once its attachments are discarded and it
 * no longer participates in the conflict: the triggering condition of the cards reacting to a character leaving play.
 * Not logged: the transition writes its entry.
 */
final readonly class CardLeftPlayEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        /**
         * The player who controlled it in play.
         */
        public Player $controller,
        /**
         * It was a character in play, not an attachment.
         */
        public bool $character,
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
        return 'card_left_play';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId()];
    }
}
