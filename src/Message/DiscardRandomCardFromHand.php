<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Discards cards chosen at random by the engine from the hand of a player (e.g. the earth ring effect), one after the
 * other; the whole hand when it holds fewer cards.
 */
#[AppAction]
final readonly class DiscardRandomCardFromHand implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who discards cards from their hand.')]
        private string $targetPlayerId,
        #[Assert\Positive]
        #[Description('Number of cards to discard.')]
        private int $quantity = 1,
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

    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
