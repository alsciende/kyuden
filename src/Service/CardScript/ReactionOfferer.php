<?php

declare(strict_types=1);

namespace App\Service\CardScript;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Type;
use App\Event\TriggeringEventInterface;
use App\Exception\Rules\RulesException;
use App\Model\ReactionOffer;
use App\Service\AbilityService;
use App\Service\PlayService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use App\State\Model\ReactionWindow;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Opens a reaction window once a triggering condition has occurred (TriggeringEventInterface): every player is offered
 * the cards that can react to it. Listens to every event the scripts subscribe to (App\Kernel), after its other
 * listeners, so that the cards read the state once the triggering condition has resolved.
 *
 * For each player, the cards asked are their active cards (ActiveCardsResolver: theirs, whose face they know) whose
 * script subscribes to the event, and that they can use where they are: an event in their hand (a dynasty event in its
 * province during the dynasty phase), the abilities of a card
 * where the engine accepts that they trigger them (AbilityService). The script decides whether the card reacts, and
 * whether its reaction is forced. The window offers, it blocks nothing: the reactions are accepted anywhere, the
 * players do not have to answer.
 */
final readonly class ReactionOfferer
{
    /**
     * @param array<class-string<TriggeringEventInterface>, array<string, string>> $subscriptions the method of the
     *                                                                                            script, by code of
     *                                                                                            card, by class of
     *                                                                                            event
     */
    public function __construct(
        private ActiveCardsResolver $activeCardsResolver,
        private AbilityService $abilityService,
        private PlayService $playService,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        #[Autowire(param: '.app.card_script_subscriptions')]
        private array $subscriptions,
    ) {
    }

    public function __invoke(TriggeringEventInterface $event): void
    {
        $methods = $this->subscriptions[$event::class] ?? [];
        if ($methods === []) {
            return;
        }

        $game = $event->getGame();
        $offers = [];
        foreach ($this->gameStateManager->getState($game)->playerIds as $playerId) {
            $player = $game->getPlayer($playerId);
            $cardIds = [];
            $forcedCardIds = [];
            foreach ($this->activeCardsResolver->resolve($game, $player) as $activeCard) {
                $method = $methods[$activeCard->card->getLogicalCard()->getId()] ?? null;
                if ($method === null || ! $this->isUsable($activeCard->card, $player)) {
                    continue;
                }

                foreach ($this->call($activeCard->script, $method, $event, $activeCard->card) as $offer) {
                    $cardIds[] = $offer->card->getId();
                    if ($offer->forced) {
                        $forcedCardIds[] = $offer->card->getId();
                    }
                }
            }

            if ($cardIds !== []) {
                // sorted, so that the state does not depend on the order in which the cards are loaded
                $cardIds = array_values(array_unique($cardIds));
                sort($cardIds);
                $forcedCardIds = array_values(array_unique($forcedCardIds));
                sort($forcedCardIds);
                $offers[$playerId] = [$cardIds, $forcedCardIds];
            }
        }

        if ($offers === []) {
            return;
        }

        $id = '';
        $this->gameStateManager->withState($game, function (GameState $state) use (&$id): GameState {
            $id = (string) ++$state->reactionWindowCount;

            return $state;
        });
        foreach ($offers as $playerId => [$cardIds, $forcedCardIds]) {
            $window = new ReactionWindow($id, $event::getTriggerName(), $event->getTriggerParams(), $cardIds, $forcedCardIds);
            $this->playerStateManager->withState(
                $game->getPlayer($playerId),
                fn (PlayerState $state): PlayerState => $state->openReactionWindow($window),
            );
        }
    }

    /**
     * An event of the conflict deck is played from the hand (an effect allowing it from elsewhere is not known), a
     * dynasty event from its province during the dynasty phase (PlayService); the abilities of a card are triggered
     * where the engine accepts them.
     */
    private function isUsable(PhysicalCard $card, Player $player): bool
    {
        if ($card->getLogicalCard()->getType() === Type::Event) {
            return $card instanceof DynastyCard
                ? $this->isDynastyEventPlayable($card)
                : $card->getCurrentPlace() === ConflictCard::STATE_HAND;
        }

        return $this->abilityService->canTrigger($card, $player);
    }

    private function isDynastyEventPlayable(DynastyCard $card): bool
    {
        try {
            $this->playService->assertEventPlayable($card);
        } catch (RulesException) {
            return false;
        }

        return true;
    }

    /**
     * @return list<ReactionOffer>
     */
    private function call(object $script, string $method, TriggeringEventInterface $event, PhysicalCard $card): array
    {
        $callable = [$script, $method];
        if (! is_callable($callable)) {
            throw new \LogicException(\sprintf('%s::%s() cannot be called.', $script::class, $method));
        }

        $offers = $callable($event, $card, $event->getGame());
        if (! is_array($offers) || ! array_is_list($offers)) {
            throw new \LogicException(\sprintf('%s::%s() must return a list of %s.', $script::class, $method, ReactionOffer::class));
        }

        foreach ($offers as $offer) {
            if (! $offer instanceof ReactionOffer) {
                throw new \LogicException(\sprintf('%s::%s() must return a list of %s.', $script::class, $method, ReactionOffer::class));
            }
        }

        /** @var list<ReactionOffer> $offers */
        return $offers;
    }
}
