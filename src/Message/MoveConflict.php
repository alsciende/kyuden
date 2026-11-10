<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Moves the conflict being resolved, with its contested ring, to another province of the defending player, which is now
 * the attacked province in place of the province the ring leaves (Chasing the Sun, Talisman of the Sun, Matsu Agetoki,
 * Shrug Off Despair, Golden Plains). The other provinces the conflict is also at stay attacked. A facedown province is
 * revealed with RevealProvince, which every player waits for: nobody passes nor ends what they resolve meanwhile.
 *
 * Accepted from any player during a conflict: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class MoveConflict implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Unbroken province of the defending player, not attacked yet.')]
        private string $provinceId,
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

    public function getProvinceId(): string
    {
        return $this->provinceId;
    }
}
