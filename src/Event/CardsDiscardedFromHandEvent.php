<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * Cards have been discarded from the hand of a player, by the cost or the effect of a card ability (DiscardCard,
 * DiscardRandomCardFromHand), once per message ("1 or more cards"): the triggering condition of the cards reacting to
 * it. Not logged: each transition writes its entry.
 */
final readonly class CardsDiscardedFromHandEvent implements TriggeringEventInterface
{
    /**
     * @param list<PhysicalCard> $cards
     */
    public function __construct(
        public Game $game,
        /**
         * The player whose hand it was.
         */
        public Player $player,
        public array $cards,
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
        return 'cards_discarded_from_hand';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId()];
    }
}
