<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\Element;

/**
 * Switches the contested ring of the conflict being resolved with a ring of the unclaimed or claimed ring pool of the
 * attacking player, e.g. to resolve an effect (Elemental Fury): the contested ring takes its place in that pool, and
 * the attacking player takes the fate on the new contested ring.
 */
#[AppAction]
final readonly class SwitchContestedRing implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Ring that becomes the contested ring, unclaimed or claimed by the attacking player.')]
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
