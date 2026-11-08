<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Turns an unbroken face-up province face down, of any player, e.g. to resolve an effect (Kitsuki Yaruma, Saadiyah
 * al-Mozedu, Untamed Steppe, Traveling Philosopher, Hidden Mountain Pass). The province keeps what lies on it: its
 * cards, its tokens. An Eminent province cannot be turned face down: the engine does not check it.
 *
 * The province can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class TurnProvinceFacedown implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Unbroken face-up province, of any player.')]
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
