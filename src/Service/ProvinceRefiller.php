<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\Enum\StackType;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Fills the provinces with the top card of the dynasty deck of their owner, face down (face up for the effects that
 * fill a province face up: FillProvince).
 * An empty deck is replaced by the shuffled discard pile (DrawDeckService); when the discard pile is empty too, the
 * province stays empty.
 *
 * A province a dynasty card has left is refilled once the effect is resolved, if it is still empty: "After a card is
 * removed from a province for any reason (and after all reaction opportunities to that card leaving the province are
 * passed), a player automatically refills the province". The handlers schedule it (`schedule()`), and
 * ProvinceRefillMiddleware refills the scheduled provinces after each message once nobody resolves what they have
 * played (an action, an interrupt or a reaction) and no duel is being resolved (`refillScheduled()`).
 */
readonly class ProvinceRefiller
{
    public function __construct(
        #[Target('dynasty_card')]
        private WorkflowInterface $dynastyCardStateMachine,
        private DrawDeckService $drawDeckService,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    /**
     * A dynasty card has left the stack: refill it later, if it is a regular province still empty by then.
     */
    public function schedule(Stack $stack): void
    {
        if ($stack->getType() !== StackType::RegularProvince) {
            return;
        }

        $this->gameStateManager->withState($stack->getPlayer()->getGame(), function (GameState $state) use ($stack): GameState {
            if (! in_array($stack->getId(), $state->provincesToRefill, true)) {
                $state->provincesToRefill[] = $stack->getId();
            }

            return $state;
        });
    }

    /**
     * Refills the scheduled provinces that are still empty, unless something is being resolved.
     */
    public function refillScheduled(Game $game): void
    {
        $state = $this->gameStateManager->getState($game);
        if ($state->provincesToRefill === [] || $state->duel !== null) {
            return;
        }

        foreach ($game->getPlayers() as $player) {
            if ($this->playerStateManager->getState($player)->isResolving()) {
                return;
            }
        }

        $stackIds = $state->provincesToRefill;
        $this->gameStateManager->withState($game, function (GameState $state): GameState {
            $state->provincesToRefill = [];

            return $state;
        });
        foreach ($game->getPlayers() as $player) {
            foreach ($player->getStacks(StackType::RegularProvince) as $stack) {
                if (in_array($stack->getId(), $stackIds, true)) {
                    $this->refillIfEmpty($stack);
                }
            }
        }
    }

    /**
     * Puts the top card of the dynasty deck of the owner of the province on it, face down unless `$faceUp`, whatever
     * the cards already on it.
     */
    public function fill(PhysicalCard $province, bool $faceUp = false): void
    {
        $card = $this->drawDeckService->getTopCard($province->getPlayer(), StackType::DynastyDrawDeck);
        if (! $card instanceof DynastyCard) {
            return;
        }

        $this->dynastyCardStateMachine->apply($card, $faceUp ? 'draw_card_faceup' : 'draw_card', [
            'stack' => $province->getStack(),
        ]);
    }

    /**
     * Refills a province a dynasty card has left, once no dynasty card remains on it (a holding keeps it from being
     * refilled), at once: for the framework steps (the fate phase).
     *
     * @param Stack $stack the stack the dynasty card has left; nothing happens unless it is a regular province
     */
    public function refillIfEmpty(Stack $stack): void
    {
        if ($stack->getType() !== StackType::RegularProvince) {
            return;
        }

        $province = null;
        foreach ($stack->getCards() as $card) {
            if ($card instanceof DynastyCard) {
                return;
            }

            if ($card instanceof ProvinceCard) {
                $province = $card;
            }
        }

        if ($province instanceof ProvinceCard) {
            $this->fill($province);
        }
    }
}
