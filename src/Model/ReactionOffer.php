<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\PhysicalCard;

/**
 * A reaction a card can trigger to what has just happened, as its script tells it (AbstractCardScript, the methods of
 * getSubscribedEvents()): the card offered to its controller. A forced reaction ("Forced Reaction:") is offered too,
 * marked: its controller cannot decline it, and the web client moves the game on for nobody until it is triggered.
 */
final readonly class ReactionOffer
{
    public function __construct(
        public PhysicalCard $card,
        /**
         * A forced reaction, not the choice of its controller.
         */
        public bool $forced = false,
    ) {
    }
}
