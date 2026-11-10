<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The active player plays a character as an attachment during an action window, attached to a character in play of any
 * player (Tattooed Wanderer, Togashi Kazue, Serene Ise Zumi…; Voice of the Ancestors from the dynasty discard pile):
 * from where a character is played (a hand, a deck, a discard pile, a province, set aside), of any player. The card is
 * an attachment while it is attached: it does not participate in conflicts, and its printed skills do not modify those
 * of the character (glossary, Attachment Cards); it is discarded when that character leaves play, unless a player
 * detaches it first (`DetachAsCharacter`: Promising Youth). The player controls it, whoever owns it, and says what they
 * pay (`cardCost`), since card effects may modify costs. The player resolves what follows with other actions, then ends
 * their action (`EndResolution`), which ends their opportunity to act.
 */
#[AppAction(special: true)]
final readonly class PlayCharacterAsAttachment implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character played as an attachment: in a hand, a deck, a discard pile, face up in a province, or set aside.')]
        private string $cardId,
        #[Description('Character in play the card is attached to.')]
        private string $targetCardId,
        #[Description('Fate paid for the card.')]
        #[Assert\PositiveOrZero]
        private int $cardCost,
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

    public function getTargetCardId(): string
    {
        return $this->targetCardId;
    }

    public function getCardCost(): int
    {
        return $this->cardCost;
    }
}
