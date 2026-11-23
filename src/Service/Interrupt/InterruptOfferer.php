<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\CardScript\AbstractCardScript;
use App\Entity\CardTypes\ConflictCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\InterruptStage;
use App\Enum\StackType;
use App\Enum\Type;
use App\GameLog\GameLogger;
use App\Message\ActionInterface;
use App\Model\InterruptOffer;
use App\Service\AbilityService;
use App\Service\CardScript\ActiveCardsResolver;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\ImminentTriggerRecord;
use App\State\Model\InterruptWindow;
use App\State\Model\InterruptWindowStage;
use App\Trigger\ImminentTriggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Opens an interrupt window before imminent triggering conditions occur, when some player is asked: what would make
 * them occur waits in it (a message, an automatic transition), see InterruptWindowService.
 *
 * Whom to ask depends on what every player sees only, so that a stop tells the opponent nothing they cannot see:
 *
 * - a card in sight of the opponent (in play, face up in a province, the role, a discard pile…): its controller is
 *   asked when its script offers it (AbstractCardScript, the methods of getSubscribedEvents() for an imminent
 *   condition) and the engine accepts that they trigger it there (AbilityService);
 * - a card out of sight (the hand): its owner is asked when its script offers a copy in their hand or their conflict
 *   deck, whichever, the condition reading the public state; the copy in the hand is offered, the one in the deck only
 *   asks (the window then offers nothing but passing). Their client passes for them after a while (`timedPlayerIds`).
 */
final readonly class InterruptOfferer
{
    /**
     * @param array<class-string<ImminentTriggerInterface>, array<string, string>> $subscriptions the method of the
     *                                                                                            script, by code of
     *                                                                                            card, by class of
     *                                                                                            condition
     */
    public function __construct(
        private ActiveCardsResolver $activeCardsResolver,
        private CardScriptRegistry $cardScriptRegistry,
        private AbilityService $abilityService,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private GameLogger $gameLogger,
        private SerializerInterface $serializer,
        #[Autowire(param: '.app.card_script_interrupt_subscriptions')]
        private array $subscriptions,
    ) {
    }

    /**
     * @param list<ImminentTriggerInterface> $triggers
     *
     * @return bool whether a window has opened: what would make the conditions occur must wait for it
     */
    public function open(Game $game, array $triggers, ?ActionInterface $message = null, ?string $transition = null): bool
    {
        $state = $this->gameStateManager->getState($game);
        $stages = [];
        foreach (InterruptStage::cases() as $stage) {
            $stages[$stage->value] = new InterruptWindowStage($stage);
        }

        $waiting = $this->waitingCardIds($state);
        foreach ($state->playerIds as $playerId) {
            $player = $game->getPlayer($playerId);
            foreach ($this->visibleOffers($game, $player, $triggers) as $offer) {
                if (! in_array($offer->card->getId(), $waiting, true)) {
                    $this->add($stages[$offer->stage()->value], $player, $offer, false);
                }
            }

            foreach ($this->hiddenOffers($game, $player, $triggers) as $offer) {
                if (in_array($offer->card->getId(), $waiting, true)) {
                    continue;
                }

                $inHand = $offer->card->getStack()->getType() === StackType::Hand;
                $this->add($stages[$offer->stage()->value], $player, $offer, true, $inHand);
            }
        }

        $stages = array_values(array_filter($stages, fn (InterruptWindowStage $stage): bool => $stage->askedPlayerIds !== []));
        if ($stages === []) {
            return false;
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($game, $stages, $triggers, $message, $transition): GameState {
            $window = new InterruptWindow((string) ++$state->reactionWindowCount);
            $window->triggers = array_map(
                fn (ImminentTriggerInterface $trigger): ImminentTriggerRecord => new ImminentTriggerRecord($trigger::getTriggerName(), $trigger->getTriggerParams(), $trigger::isResolvedWhenCancelled()),
                $triggers,
            );
            $window->stages = $stages;
            foreach ($state->playerIds as $playerId) {
                $window->resolutionBase[$playerId] = $this->playerStateManager->getState($game->getPlayer($playerId))->countInterruptsAndReactions();
            }

            if ($message instanceof ActionInterface) {
                $window->messageName = $message::class;
                $window->messagePayload = $this->serializer->serialize($message, 'json');
            }

            $window->transition = $transition;
            $state->interruptWindows[] = $window;

            return $state;
        });

        foreach ($triggers as $trigger) {
            $this->gameLogger->log($game, $trigger->getLogTemplate(), $trigger->getLogParams());
        }

        return true;
    }

    /**
     * The cards whose play waits in a window open (an interrupt played in it, still in the hand): not offered again.
     *
     * @return list<string>
     */
    private function waitingCardIds(GameState $state): array
    {
        $cardIds = [];
        foreach ($state->interruptWindows as $window) {
            foreach ($window->triggers as $trigger) {
                if ($trigger->resolvedWhenCancelled && isset($trigger->params['card'])) {
                    $cardIds[] = $trigger->params['card'];
                }
            }
        }

        return $cardIds;
    }

    private function add(InterruptWindowStage $stage, Player $player, InterruptOffer $offer, bool $timed, bool $offered = true): void
    {
        $playerId = $player->getId();
        if (! in_array($playerId, $stage->askedPlayerIds, true)) {
            $stage->askedPlayerIds[] = $playerId;
        }

        if ($timed && ! in_array($playerId, $stage->timedPlayerIds, true)) {
            $stage->timedPlayerIds[] = $playerId;
        }

        if (! $offered) {
            return;
        }

        $cardId = $offer->card->getId();
        $cardIds = $stage->cardIds[$playerId] ?? [];
        if (! in_array($cardId, $cardIds, true)) {
            $cardIds[] = $cardId;
            // sorted, so that the state does not depend on the order in which the cards are loaded
            sort($cardIds);
            $stage->cardIds[$playerId] = $cardIds;
        }

        if ($offer->forced) {
            $forcedCardIds = $stage->forcedCardIds[$playerId] ?? [];
            if (! in_array($cardId, $forcedCardIds, true)) {
                $forcedCardIds[] = $cardId;
                sort($forcedCardIds);
                $stage->forcedCardIds[$playerId] = $forcedCardIds;
            }
        }
    }

    /**
     * The offers of the active cards of the player in sight of their opponent (not in their hand) that they can trigger
     * where they are.
     *
     * @param list<ImminentTriggerInterface> $triggers
     *
     * @return list<InterruptOffer>
     */
    private function visibleOffers(Game $game, Player $player, array $triggers): array
    {
        $offers = [];
        foreach ($this->activeCardsResolver->resolve($game, $player) as $activeCard) {
            $card = $activeCard->card;
            if ($card->getStack()->getType() === StackType::Hand
                || $card->getLogicalCard()->getType() === Type::Event
                || ! $this->abilityService->canTrigger($card, $player)) {
                continue;
            }

            $offers = [...$offers, ...$this->ask($activeCard->script, $card, $game, $triggers)];
        }

        return $offers;
    }

    /**
     * The offers of the cards of the player out of sight of their opponent: the events of their hand and of their
     * conflict deck, and the other cards of their hand whose abilities their script lets them trigger from there.
     *
     * @param list<ImminentTriggerInterface> $triggers
     *
     * @return list<InterruptOffer>
     */
    private function hiddenOffers(Game $game, Player $player, array $triggers): array
    {
        $offers = [];
        foreach ([StackType::Hand, StackType::ConflictDrawDeck] as $stackType) {
            foreach ($player->getStacks($stackType) as $stack) {
                foreach ($stack->getCards() as $card) {
                    if (! $card instanceof ConflictCard) {
                        continue;
                    }

                    $script = $this->cardScriptRegistry->find($card->getLogicalCard()->getId());
                    if (! $script instanceof AbstractCardScript || ! $this->isUsableFromHand($script, $card, $player, $stackType)) {
                        continue;
                    }

                    $offers = [...$offers, ...$this->ask($script, $card, $game, $triggers)];
                }
            }
        }

        return $offers;
    }

    /**
     * An event, or a card whose abilities its script lets its player trigger from the hand: in the hand, where they
     * can trigger it now; in the deck, a copy that would be, so that the deck asks as the hand does (Yogo Kikuyo).
     */
    private function isUsableFromHand(AbstractCardScript $script, PhysicalCard $card, Player $player, StackType $stackType): bool
    {
        if ($card->getLogicalCard()->getType() === Type::Event) {
            return true;
        }

        return $stackType === StackType::Hand
            ? $this->abilityService->canTrigger($card, $player)
            : in_array(StackType::Hand, $script->getAbilityStacksOutOfPlay(), true);
    }

    /**
     * @param list<ImminentTriggerInterface> $triggers
     *
     * @return list<InterruptOffer>
     */
    private function ask(AbstractCardScript $script, PhysicalCard $card, Game $game, array $triggers): array
    {
        $offers = [];
        foreach ($triggers as $trigger) {
            $method = $this->subscriptions[$trigger::class][$card->getLogicalCard()->getId()] ?? null;
            if ($method === null) {
                continue;
            }

            $callable = [$script, $method];
            if (! is_callable($callable)) {
                throw new \LogicException(\sprintf('%s::%s() cannot be called.', $script::class, $method));
            }

            $answer = $callable($trigger, $card, $game);
            if (! is_array($answer)) {
                throw new \LogicException(\sprintf('%s::%s() must return a list of %s.', $script::class, $method, InterruptOffer::class));
            }

            foreach ($answer as $offer) {
                if (! $offer instanceof InterruptOffer) {
                    throw new \LogicException(\sprintf('%s::%s() must return a list of %s.', $script::class, $method, InterruptOffer::class));
                }

                $offers[] = $offer;
            }
        }

        return $offers;
    }
}
