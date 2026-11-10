<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mulligans dynasty cards during setup (glossary, Mulligan): the cards chosen among the face-down cards of the provinces of the player are set aside, each province is filled again from the top of their dynasty deck, then the cards set aside are shuffled back into it. Once only, in player order; no card keeps the cards. The player then looks at the cards of their provinces again: they may not afterwards.
 */
#[AppAction]
final readonly class MulliganDynastyCards implements PlayerActionInterface
{
    /**
     * @param list<string> $cardIds
     */
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Face-down dynasty cards in regular provinces of the player, those to set aside; none to keep every card.')]
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
