<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\ConflictType;
use App\Enum\Element;

/**
 * The target player claims a ring they have not claimed, e.g. to resolve an effect: unclaimed or contested (Display of
 * Power), or taken from the claimed ring pool of another player (Roving Michibiku). It goes to their claimed ring pool,
 * with the fate on it. The ring is claimed in `conflict_type`, by default the type of the conflict it was claimed in
 * (a ring taken), else the type of the conflict being resolved; outside of a conflict, `conflict_type` is required for
 * a ring nobody has claimed. A contested ring claimed this way is not claimed again by the winner of the conflict.
 */
#[AppAction(special: true)]
final readonly class ClaimRing implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Ring to claim: unclaimed, contested or claimed by another player.')]
        private Element $ring,
        #[Description('Player who claims the ring.')]
        private string $targetPlayerId,
        #[Description('Type of the conflict the ring is claimed in; by default, the type it was claimed in (taken from another player), else the type of the conflict being resolved.')]
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
