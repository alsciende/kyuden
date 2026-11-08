<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\HiddenPile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The sender reveals cards some players do not see to every player: the top cards of a deck (`quantity`, the whole
 * deck by default), a hand or the dynasty cards in the provinces, of any player, e.g. to resolve an effect (The
 * Perfect Gift, Artisan Academy). The log gives the cards to every player; nothing is recorded: the cards are out of
 * sight again afterwards.
 */
#[AppAction(special: true)]
final readonly class RevealCards implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player whose deck, hand or provinces it is.')]
        private string $targetPlayerId,
        #[Description('Deck, hand, or the dynasty cards in the provinces.')]
        private HiddenPile $stack,
        #[Description('Number of cards from the top of a deck; the whole stack by default; not for the provinces.')]
        #[Assert\Positive]
        private ?int $quantity = null,
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

    /**
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getStack(): HiddenPile
    {
        return $this->stack;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }
}
