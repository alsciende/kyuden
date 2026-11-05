<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The active player plays a character during an action window, at home or, during a conflict in which they take part,
 * into the conflict on their side. From their hand, or from elsewhere when a card allows it (Prepared Ambush, Gateway
 * to Meido, Artisan Academy…): a conflict character of a hand, a conflict deck (a card looked at) or a conflict
 * discard pile, a dynasty character face up in a province, of a dynasty deck (a card looked at) or of a dynasty
 * discard pile, of any player. The player controls the character, whoever owns it. During the dynasty phase,
 * characters are played from provinces with `PlayCharacterFromProvince`.
 *
 * The player says what they pay, since card effects may modify costs: `cardCost` for the card, `fateCost` for the
 * additional fate, and `fate` is the number of fate placed on the character.
 * The player resolves what follows with other actions, then ends their action (`EndResolution`), which ends
 * their opportunity to act.
 */
#[AppAction]
final readonly class PlayCharacter implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character played: in a hand, a deck or a discard pile, or face up in a province.')]
        private string $cardId,
        #[Description('Fate paid for the card.')]
        #[Assert\PositiveOrZero]
        private int $cardCost,
        #[Description('Fate paid for the additional fate placed on the character.')]
        #[Assert\PositiveOrZero]
        private int $fateCost,
        #[Description('Fate placed on the character.')]
        #[Assert\PositiveOrZero]
        private int $fate,
        #[Description('Whether the character enters play into the conflict, on the side of the player.')]
        private bool $intoConflict,
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

    public function getCardCost(): int
    {
        return $this->cardCost;
    }

    public function getFateCost(): int
    {
        return $this->fateCost;
    }

    public function getFate(): int
    {
        return $this->fate;
    }

    public function isIntoConflict(): bool
    {
        return $this->intoConflict;
    }
}
