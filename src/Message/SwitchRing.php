<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\Element;

/**
 * Switches the places of two rings, among the contested ring, the unclaimed ring pool and the claimed ring pools, e.g.
 * to resolve an effect (Elemental Fury, Know the World). A ring that becomes the contested ring keeps the type of the
 * conflict, and the attacking player takes the fate on it; a ring that goes to a claimed ring pool takes the conflict
 * type of the ring it replaces. During a conflict, the contested ring is only switched with a ring of the unclaimed or
 * claimed ring pool of the attacking player. The fate on the other rings stays on them.
 */
#[AppAction]
final readonly class SwitchRing implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Ring to switch.')]
        private Element $ring,
        #[Description('Ring it is switched with, from another place.')]
        private Element $otherRing,
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

    public function getOtherRing(): Element
    {
        return $this->otherRing;
    }
}
