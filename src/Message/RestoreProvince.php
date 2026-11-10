<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Restores a broken province: it is face up and unbroken again (Ethereal Alignment, Public Forum, Akodo Toshiro). A
 * broken stronghold province ends the game at once: it cannot be restored.
 *
 * The province can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class RestoreProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Broken province, of any player.')]
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
