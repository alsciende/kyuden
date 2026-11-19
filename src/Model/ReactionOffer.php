<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\PhysicalCard;

/**
 * A reaction a card can trigger to what has just happened, as its script tells it (AbstractCardScript, the methods of
 * getSubscribedEvents()): the card offered to its controller.
 */
final readonly class ReactionOffer
{
    public function __construct(
        public PhysicalCard $card,
    ) {
    }
}
