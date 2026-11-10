<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mulligans conflict cards during setup (glossary, Mulligan): the cards chosen in the starting hand of the player are set aside, as many cards are drawn from the top of their conflict deck, then the cards set aside are shuffled back into it. Once only, in player order; no card keeps the cards.
 */
#[AppAction]
final readonly class MulliganConflictCards implements PlayerActionInterface
{
    /**
     * @param list<string> $cardIds
     */
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Cards in the hand of the player, those to set aside; none to keep every card.')]
        #[Assert\Unique]
        private array $cardIds = [],
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
     * @return list<string>
     */
    public function getCardIds(): array
    {
        return $this->cardIds;
    }
}
