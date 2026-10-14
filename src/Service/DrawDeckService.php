<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Draw decks (conflict and dynasty) and the rule of running out of cards: a player who attempts to draw a card or to
 * (re)fill a province from an empty deck loses 5 honor, then shuffles the corresponding discard pile to form a new deck.
 */
readonly class DrawDeckService
{
    public const int RUNNING_OUT_OF_CARDS_HONOR_LOSS = 5;

    public function __construct(
        private PlayerStateManager $playerStateManager,
        private WorkflowInterface $conflictCardStateMachine,
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    /**
     * @return PhysicalCard|null the top card of the deck, after forming a new deck if it was empty;
     *                           null if the discard pile was empty too
     */
    public function getTopCard(Player $player, StackType $deckType): ?PhysicalCard
    {
        $deck = $player->getUniqueStack($deckType);
        if ($deck->getCards()->isEmpty()) {
            $this->formNewDeck($player, $deckType);
        }

        return $deck->getTopCard();
    }

    private function formNewDeck(Player $player, StackType $deckType): void
    {
        [$discardPileType, $workflow] = match ($deckType) {
            StackType::ConflictDrawDeck => [StackType::ConflictDiscardPile, $this->conflictCardStateMachine],
            StackType::DynastyDrawDeck => [StackType::DynastyDiscardPile, $this->dynastyCardStateMachine],
            default => throw new \InvalidArgumentException(sprintf('%s is not a draw deck.', $deckType->name)),
        };

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->decrementHonor(self::RUNNING_OUT_OF_CARDS_HONOR_LOSS),
        );

        foreach ($player->getUniqueStack($discardPileType)->getOrderedCards() as $card) {
            $workflow->apply($card, 'put_back');
        }

        $player->getUniqueStack($deckType)->shuffle($player->getGame()->getRandomizer());
    }
}
