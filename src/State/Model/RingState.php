<?php

declare(strict_types=1);

namespace App\State\Model;

/**
 * One of the five rings, stored in GameState by element. The contested ring is the ring of the current conflict
 * (ConflictState).
 */
class RingState
{
    /**
     * Fate placed on the ring while it is unclaimed (fate phase), taken by the player who declares a conflict with it.
     */
    public int $fate = 0;

    /**
     * Player who has the ring in their claimed ring pool; null while it is unclaimed or contested.
     */
    public ?string $claimedById = null;
}
