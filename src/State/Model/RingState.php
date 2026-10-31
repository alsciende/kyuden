<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\ConflictType;

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

    /**
     * Type of the conflict in which the ring was claimed, which card effects refer to; null while it is unclaimed or
     * contested. It belongs to the place of the ring in the claimed ring pool: a ring switched into it takes it.
     */
    public ?ConflictType $claimedConflictType = null;

    public function claim(string $playerId, ConflictType $conflictType): void
    {
        $this->claimedById = $playerId;
        $this->claimedConflictType = $conflictType;
    }

    /**
     * Back to the unclaimed ring pool.
     */
    public function unclaim(): void
    {
        $this->claimedById = null;
        $this->claimedConflictType = null;
    }
}
