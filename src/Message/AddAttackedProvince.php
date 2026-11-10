<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * The conflict being resolved is also at another province of the defending player, which becomes an attacked province
 * beside the others (A War on Two Fronts): each one is broken separately. A facedown province is revealed with
 * RevealProvince, which every player waits for: nobody passes nor ends what they resolve meanwhile.
 *
 * Accepted from any player during a conflict: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class AddAttackedProvince implements PlayerActionInterface
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
