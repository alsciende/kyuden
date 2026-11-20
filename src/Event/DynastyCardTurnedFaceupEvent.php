<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;

/**
 * A dynasty card has been turned face up in its province (transition `turn_faceup`: revealed in the dynasty phase, or
 * by an effect): the triggering condition of the cards reacting to it ("After this holding is turned faceup"). Not
 * logged: the transition writes its entry.
 */
final readonly class DynastyCardTurnedFaceupEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public DynastyCard $card,
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
        return 'dynasty_card_turned_faceup';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId()];
    }
}
