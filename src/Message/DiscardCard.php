<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Discards a card from hand, from play or from a province to its owner's discard pile, e.g. a dynasty card from
 * a broken province or during the fate phase, a card discarded by an effect, a character with no fate.
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
#[AppAction]
final readonly class DiscardCard implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card to discard, from hand, from play or from a province.')]
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
