<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * Switches the type of the conflict being resolved, military to political or political to military, e.g. to resolve
 * an effect (Rally to the Cause). The conflict opportunity used by its declaration does not change.
 */
#[AppAction]
final readonly class SwitchConflictType implements PlayerActionInterface
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
