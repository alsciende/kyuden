<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\Element;

/**
 * Returns a claimed ring to the unclaimed ring pool, with the fate on it (Deduction, Overlooked Community, Severed from
 * the Stream: one message per ring). The contested ring is not returned: it stays the ring of the conflict until the
 * conflict ends; a ring claimed as the conflict resolves is returned once claimed (Henshin Mysteries).
 */
#[AppAction(special: true)]
final readonly class ReturnRing implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Ring to return, in the claimed ring pool of a player.')]
        private Element $ring,
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
}
