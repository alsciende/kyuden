<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\ConflictType;
use App\Enum\Element;

/**
 * The target player claims a ring that nobody has claimed, unclaimed or contested, e.g. to resolve an effect (Display
 * of Power): it goes to their claimed ring pool, with the fate on it. The ring is claimed in `conflict_type`, by
 * default the type of the conflict being resolved; outside of a conflict, `conflict_type` is required. A contested ring
 * claimed this way is not claimed again by the winner of the conflict.
 */
#[AppAction]
final readonly class ClaimRing implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Ring to claim, unclaimed or contested.')]
        private Element $ring,
        #[Description('Player who claims the ring.')]
        private string $targetPlayerId,
        #[Description('Type of the conflict the ring is claimed in; by default, the type of the conflict being resolved.')]
        private ?ConflictType $conflictType = null,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }

    public function getRing(): Element
    {
        return $this->ring;
    }

    /**
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getConflictType(): ?ConflictType
    {
        return $this->conflictType;
    }
}
