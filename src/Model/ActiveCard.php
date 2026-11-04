<?php

declare(strict_types=1);

namespace App\Model;

use App\CardScript\AbstractCardScript;
use App\Entity\PhysicalCard;

/**
 * An active card of a player (ActiveCardsResolver) and its script.
 */
final readonly class ActiveCard
{
    public function __construct(
        public PhysicalCard $card,
        public AbstractCardScript $script,
    ) {
    }
}
