<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\PhysicalCard;
use App\State\Model\ReactionWindow;

/**
 * A forced reaction its controller has to trigger (ForcedReactionsResolver): the card, and the window offering it.
 */
final readonly class PendingForcedReaction
{
    public function __construct(
        public PhysicalCard $card,
        public ReactionWindow $window,
    ) {
    }
}
