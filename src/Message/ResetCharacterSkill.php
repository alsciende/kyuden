<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Clears the skills set on a character in play (`SetCharacterSkill`): the character is back to its printed military
 * and political skills, e.g. once the effects modifying them have expired.
 *
 * The character can belong to any player.
 */
#[AppAction]
final readonly class ResetCharacterSkill implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character in play.')]
        private string $cardId,
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

    public function getCardId(): string
    {
        return $this->cardId;
    }
}
