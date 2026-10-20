<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The player has resolved what they played, once its effects are applied with other messages. An interrupt or a
 * reaction ends first, the last one initiated first, without changing the opportunity to act. Otherwise the active
 * player ends the action they have taken (a card played, an action ability triggered): their opportunity to act
 * ends, and the next player who has not passed gets one. A player who has played nothing passes (PassOpportunity).
 */
#[AppAction]
final readonly class EndResolution implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
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
}
