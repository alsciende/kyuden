<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Breaks a face-up province outside of the resolution of a conflict, e.g. to resolve an effect (Endless Plains). A
 * broken stronghold province eliminates its player.
 *
 * The province can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class BreakProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Face-up unbroken province.')]
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
