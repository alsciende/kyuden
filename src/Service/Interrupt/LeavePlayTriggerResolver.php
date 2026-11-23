<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Exception\Data\DataException;
use App\Exception\Rules\RulesException;
use App\Message\ActionInterface;
use App\Message\DiscardCard;
use App\Message\PlaceCardUnder;
use App\Message\PlayerActionInterface;
use App\Message\PutCardIntoProvince;
use App\Message\PutCardOnDeck;
use App\Message\RemoveFromGame;
use App\Message\ReturnCardToHand;
use App\Message\SacrificeCard;
use App\Message\ShuffleIntoDeck;
use App\Repository\CardRepository;
use App\Service\AvailableActionsResolver;
use App\Service\ControlService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\Trigger\CardLeavingPlayTrigger;
use Symfony\Component\Workflow\Exception\ExceptionInterface as WorkflowException;

/**
 * A card in play would leave play (CardLeavingPlayTrigger): the effects moving it out of play, and the discard of the
 * characters without fate in the fate phase (`characters_discarded`). A character takes its attachments along.
 */
final readonly class LeavePlayTriggerResolver implements ImminentTriggerResolverInterface
{
    /**
     * The effects moving a card out of play, from any place they take it.
     */
    private const array MESSAGES = [
        DiscardCard::class,
        SacrificeCard::class,
        ReturnCardToHand::class,
        PutCardOnDeck::class,
        ShuffleIntoDeck::class,
        RemoveFromGame::class,
        PlaceCardUnder::class,
        PutCardIntoProvince::class,
    ];

    public function __construct(
        private CardRepository $cardRepository,
        private AvailableActionsResolver $availableActionsResolver,
        private ControlService $controlService,
        private CardStateManager $cardStateManager,
        private GameStateManager $gameStateManager,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        if (! $message instanceof PlayerActionInterface || ! in_array($message::class, self::MESSAGES, true)) {
            return [];
        }

        \assert(method_exists($message, 'getCardId'));
        $cardId = $message->getCardId();
        \assert(is_string($cardId));
        try {
            $card = $this->cardRepository->get($cardId, $game);
            $this->availableActionsResolver->getHandler($message::class)->assertAvailable($game, $game->getPlayer($message->getPlayerId()));
        } catch (DataException|RulesException|WorkflowException) {
            // refused by its handler: nothing would occur
            return [];
        }

        return $this->isInPlay($card) ? $this->leaving($card, $message instanceof SacrificeCard) : [];
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        if ($transition !== 'characters_discarded') {
            return [];
        }

        $triggers = [];
        foreach ($this->gameStateManager->getState($game)->playerIdsFromFirstPlayer() as $playerId) {
            foreach ($game->getPlayer($playerId)->getCharactersInPlay() as $character) {
                if ($this->cardStateManager->getState($character)->getFate() === 0) {
                    $triggers = [...$triggers, ...$this->leaving($character, false)];
                }
            }
        }

        return $triggers;
    }

    /**
     * A character or an attachment in play (the role and the stronghold never leave it).
     */
    private function isInPlay(PhysicalCard $card): bool
    {
        return ($card instanceof ConflictCard || $card instanceof DynastyCard)
            && in_array($card->getCurrentPlace(), [ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED], true);
    }

    /**
     * The card, and for a character the cards attached to it, which leave play with it.
     *
     * @return list<CardLeavingPlayTrigger>
     */
    private function leaving(PhysicalCard $card, bool $sacrificed): array
    {
        $character = $card->isCharacterInPlay();
        $triggers = [new CardLeavingPlayTrigger($card, $this->controlService->getController($card), $character, $sacrificed)];
        if ($character) {
            foreach (array_slice($card->getStack()->getOrderedCards(), 1) as $attachment) {
                $triggers[] = new CardLeavingPlayTrigger($attachment, $this->controlService->getController($attachment), false, host: $card);
            }
        }

        return $triggers;
    }
}
